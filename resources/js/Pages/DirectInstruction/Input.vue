<script setup>
defineProps({
  authItems: Array
})

const listRows = [
  {
    id: '26000001',
    orderNo: '26000634',
    inputDate: '2026/02/11',
    shipDate: '2026/02/13',
    customer: 'A株式会社',
    office: '東京',
    poNo: 'AA1234',
    requestStatus: '申請済',
    arrangeStatus: '確定',
    driverStatus: '確定',
    base: '本社',
    shipped: '出荷済'
  },
  {
    id: '26000002',
    orderNo: '26001000',
    inputDate: '2026/02/12',
    shipDate: '2026/02/16',
    customer: '株式会社B',
    office: '東京',
    poNo: 'BB5678',
    requestStatus: '申請済',
    arrangeStatus: '手配中',
    driverStatus: '未定',
    base: '本社',
    shipped: '未出荷'
  },
  {
    id: '26000003',
    orderNo: '26000044',
    inputDate: '2026/02/13',
    shipDate: '2026/02/16',
    customer: 'C株式会社',
    office: '名古屋',
    poNo: 'AB5678',
    requestStatus: '申請済',
    arrangeStatus: '確定',
    driverStatus: '確定',
    base: '本社',
    shipped: '未出荷'
  },
  {
    id: '26000004',
    orderNo: '26000100',
    inputDate: '2026/02/16',
    shipDate: '2026/02/20',
    customer: '株式会社D',
    office: '名古屋',
    poNo: 'BC9012',
    requestStatus: '申請済',
    arrangeStatus: '確定',
    driverStatus: '未定',
    base: '本社',
    shipped: '未出荷'
  },
  {
    id: '26000005',
    orderNo: '26000101',
    inputDate: '2026/02/17',
    shipDate: '2026/02/20',
    customer: 'A株式会社',
    office: '名古屋',
    poNo: 'DE4567',
    requestStatus: '一時保存',
    arrangeStatus: '',
    driverStatus: '',
    base: '',
    shipped: ''
  }
]
</script>

<template>
  <section class="section dashboard direct-page">
    <ol class="breadcrumb">
      <li v-if="authItems?.[0]?.ホーム == 0">
        <router-link to="/home">ホーム</router-link>
      </li>
      <li>直送指示</li>
      <li>直送指示入力（営業）</li>
    </ol>

    <div class="card">
      <div class="contents_head direct-head">
        <h5 class="card-title">直送指示登録システム</h5>
        <button type="button" class="button_r none search od_a">新規登録</button>
      </div>

      <div class="direct-filter-wrap">
        <table class="table_w direct-filter-table">
          <tbody>
            <tr>
              <th>入力日</th>
              <td><input type="date" class="form-control normal" /></td>
              <td class="tilde">〜</td>
              <td><input type="date" class="form-control normal" /></td>
              <th>出荷日</th>
              <td><input type="date" class="form-control normal" /></td>
              <td class="tilde">〜</td>
              <td><input type="date" class="form-control normal" /></td>
            </tr>
            <tr>
              <th>得意先</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>入力</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>担当</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>受注NO</th>
              <td><input type="text" class="form-control normal" /></td>
            </tr>
            <tr>
              <th>注文NO</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>商品CD</th>
              <td><input type="text" class="form-control normal" /></td>
              <td colspan="4" class="radio-filter-cell">
                <label><input type="radio" name="orderno" checked /> 1: ・・から始まる</label>
                <label><input type="radio" name="orderno" /> 2: ・・含む</label>
              </td>
            </tr>
            <tr>
              <th>拠点</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>申請</th>
              <td><input type="text" class="form-control normal" /></td>
              <th>ステータス</th>
              <td><input type="text" class="form-control normal" /></td>
              <td colspan="2" class="exec-cell">
                <button type="button" class="button_r none search od_a max">抽出実行</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="scroll-box s scroll-box_y c">
        <table class="table_w tablesorter alter direct-list-table">
          <thead>
            <tr class="head">
              <th>直送指示No.</th>
              <th>受注NO</th>
              <th>入力日</th>
              <th>出荷日</th>
              <th>得意先</th>
              <th>営業所</th>
              <th>注文NO</th>
              <th>申請ステータス</th>
              <th>配車ステータス</th>
              <th>ドライバー情報</th>
              <th>出荷拠点</th>
              <th>出荷状況</th>
              <th>表示</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in listRows" :key="row.id">
              <td><a href="#">{{ row.id }}</a></td>
              <td>{{ row.orderNo }}</td>
              <td>{{ row.inputDate }}</td>
              <td>{{ row.shipDate }}</td>
              <td>{{ row.customer }}</td>
              <td>{{ row.office }}</td>
              <td>{{ row.poNo }}</td>
              <td>{{ row.requestStatus }}</td>
              <td :class="['status-cell', row.arrangeStatus === '確定' ? 'st-red' : row.arrangeStatus === '手配中' ? 'st-yellow' : '']">
                {{ row.arrangeStatus }}
              </td>
              <td :class="['status-cell', row.driverStatus === '確定' ? 'st-red' : row.driverStatus === '未定' ? 'st-white' : '']">
                {{ row.driverStatus }}
              </td>
              <td>{{ row.base }}</td>
              <td :class="['status-cell', row.shipped === '出荷済' ? 'st-red' : row.shipped === '未出荷' ? 'st-blue' : '']">
                {{ row.shipped }}
              </td>
              <td>
                <button type="button" class="button_r none search small">表示</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">通知イメージ</h5>
      </div>
      <div class="notify-preview">
        <div class="notify-window">
          <div class="notify-row">
            <span class="notify-date">2026/02/27</span>
            <div class="notify-chip">追加されました</div>
          </div>
          <div class="notify-bubble">
            通知：名古屋営業所岡安さんが直送指示No：26000002を新規登録しました。<br />
            ・該当する直送指示Noに遷移するリンクも送る<br />
            ・送信された通知に対して処理完了・未完了が分かる機能
          </div>
        </div>
        <div class="notify-note">
          システムから担当者へメッセージを送る<br />
          リンククリックで該当する直送指示Noへ遷移<br />
          送信された通知に対して完了/未完了が分かる表示を追加
        </div>
      </div>
    </div>

    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">注文回答書イメージ</h5>
      </div>
      <div class="answer-wrap">
        <article class="answer-sheet">
          <header>
            <p>注文回答書</p>
            <span>2024/11/21</span>
          </header>
          <table class="answer-table">
            <tbody>
              <tr><th>相手先注文NO</th><td>2024120-0004</td><th>出荷工場</th><td>東北工場</td></tr>
              <tr><th>出荷日</th><td>2024/11/21</td><th>受注者</th><td>清水</td></tr>
              <tr><th>希望出荷日</th><td>2024/11/22 AM着</td><th>送り状No.</th><td>未定</td></tr>
            </tbody>
          </table>
          <div class="answer-footer">正式なご請求金額は運賃よりご連絡いたします。</div>
          <div class="driver-box">
            ※ドライバー情報のご連絡※<br />
            会社：八幡商運 / 車番：仙台100 え 16
          </div>
        </article>

        <article class="answer-sheet">
          <header>
            <p>注文回答書</p>
            <span>2024/11/21</span>
          </header>
          <table class="answer-table">
            <tbody>
              <tr><th>相手先注文NO</th><td>2024120-0004</td><th>出荷工場</th><td>東北工場</td></tr>
              <tr><th>出荷日</th><td>2024/11/21</td><th>受注者</th><td>清水</td></tr>
              <tr><th>希望出荷日</th><td>2024/11/22 AM着</td><th>送り状No.</th><td>確定</td></tr>
            </tbody>
          </table>
          <div class="answer-footer">上記金額にてご請求金額確定となります。</div>
          <div class="driver-box">
            ※ドライバー情報のご連絡※<br />
            会社：八幡商運 / 車番：仙台100 え 16
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.direct-page :deep(.card) {
  margin-bottom: 24px;
}

.direct-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.direct-head .button_r {
  min-width: 148px;
}

.direct-filter-wrap {
  padding: 0 18px 18px;
}

.direct-filter-table {
  width: 100%;
}

.direct-filter-table th,
.direct-filter-table td {
  border: 1px solid #9ea3ad;
  padding: 8px;
  vertical-align: middle;
  background: #fff;
}

.direct-filter-table th {
  width: 120px;
  text-align: center;
  font-weight: 700;
  color: #2f3440;
  background: #f4f6f9;
}

.direct-filter-table .tilde {
  width: 44px;
  text-align: center;
  font-weight: 700;
}

.direct-filter-table .radio-filter-cell {
  font-size: 13px;
  white-space: nowrap;
}

.direct-filter-table .radio-filter-cell label {
  margin-right: 18px;
}

.direct-filter-table .exec-cell {
  text-align: center;
}

.direct-list-table td,
.direct-list-table th {
  white-space: nowrap;
}

.status-cell {
  text-align: center;
  font-weight: 700;
}

.st-red {
  background: #ff2020;
  color: #111;
}

.st-yellow {
  background: #f2c100;
  color: #111;
}

.st-blue {
  background: #1e85d9;
  color: #fff;
}

.notify-preview {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 16px;
  padding: 0 18px 18px;
}

.notify-window {
  border: 1px solid #c8ccd3;
  background: #fbfcfd;
  padding: 16px;
}

.notify-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.notify-date {
  color: #5f6775;
  font-size: 13px;
}

.notify-chip {
  background: #4f72b0;
  color: #fff;
  font-weight: 700;
  padding: 8px 12px;
  border-radius: 4px;
}

.notify-bubble {
  background: #5a7fc0;
  color: #fff;
  padding: 12px 14px;
  border-radius: 4px;
  line-height: 1.65;
}

.notify-note {
  border: 1px solid #f04545;
  display: grid;
  place-items: center;
  text-align: center;
  color: #2f3440;
  font-weight: 700;
  line-height: 1.7;
}

.answer-wrap {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  padding: 0 18px 18px;
}

.answer-sheet {
  border: 1px solid #d2d7de;
  background: #fff;
  padding: 12px;
}

.answer-sheet header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 12px;
}

.answer-sheet header p {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: #273142;
}

.answer-sheet header span {
  color: #4a5568;
  font-size: 12px;
}

.answer-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

.answer-table th,
.answer-table td {
  border: 1px solid #99a1af;
  padding: 6px 8px;
  font-size: 12px;
}

.answer-table th {
  width: 24%;
  background: #f8f9fc;
}

.answer-footer {
  border: 1px solid #222;
  text-align: center;
  padding: 6px;
  margin: 10px 0;
  font-size: 12px;
  font-weight: 700;
}

.driver-box {
  border: 2px dashed #2f3440;
  padding: 8px;
  font-size: 12px;
}

@media (max-width: 1100px) {
  .notify-preview,
  .answer-wrap {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767px) {
  .direct-filter-wrap {
    padding: 0 10px 12px;
  }

  .direct-filter-table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }

  .notify-preview,
  .answer-wrap {
    padding: 0 10px 12px;
  }
}
</style>
