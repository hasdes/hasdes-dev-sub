<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useLoading } from 'vue-loading-overlay'
import axios from 'axios'
import { ElNotification } from 'element-plus'
import { useRouter } from 'vue-router'

defineProps({
  authItems: Array
})

/* <作業状態> ********************
中断(再集荷) :7
再集荷:6
キャンセル (集荷済&キャンセル未処理) :5
キャンセル（未集荷) :4
キャンセル（処理済み）:3
集荷処理済み :2
中断 :1
未集荷 :0 
*********************************/

/* =====================
 * 基本状態
 * ===================== */
const router = useRouter()
const header = ref(null)
const details = ref([])
const viewDetails = ref([]) // 画面表示用
const rawDetails  = ref([]) // 保存用（DDそのまま）
const originNo = ref(null) // 集荷済みの元出荷指示NO
const isScanVerified = ref(false)
const loadingActive = ref(false)
const $loading = useLoading({})
const scanning = ref(false)
// 年号不一致時の一時保存
const pendingItem = ref(null)
const pendingYear = ref(null)
const pendingRow = ref(null)
const isSuspendSaving = ref(false) //中断フラグ
const scannedProductionNos = ref([])//生産管理NO
/* =====================
 * モーダル制御
 * ===================== */
const showYearModal = ref(false) //年号変更モーダル
const returnItemModal = ref(false)//返却モーダル
const showErrModal = ref(false)
const err_msg = ref('')

/* =====================
 * 前後の空白を除去
 * ===================== */
const normalizeStr = v => {
  if (v === null || v === undefined) return null
  const s = v.toString().trim()
  return s === '' ? null : s
}
/* =====================
 * URLパラメータ
 * ===================== */
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('key')
}
const getKeyFromUrl2 = () => {
  const params = new URLSearchParams(window.location.search)
  return params.get('key2')
}
/* =====================
 * エラーモーダル
 * ===================== */
const showErrorModal = (message) => {
  err_msg.value = message
  showErrModal.value = true
}
const closeErrModal = () => {
  showErrModal.value = false
}
/* =====================
 * 伝票：出荷形態
 * ===================== */
const Shipping_TYPES = {
  0: '引取',
  1: '小口',
  2: '直送',
  3: 'コンテナ'
}
const getShippingTypeName = (type) => {
  return Shipping_TYPES[Number(type)] ?? '不明'
}
/* =====================
 * 伝票：日付フォーマット
 * ===================== */
const formatDate = (value) => {
  if (!value) return ''
  const str = value.toString()
  if (str.length !== 8) return value

  return `${str.slice(0, 4)}年${str.slice(4, 6)}月${str.slice(6, 8)}日`
}
/* =====================
 * 明細:年号ヘッダ
 * ===================== */
const yearHeaders = ref([])
// 年号の“中身（データ用キー）”を作る関数 (集荷品年号の列を何年分持つかを決める,yearHeaders という配列を初期化する)
const buildYearHeaders = () => {
  const currentYear = new Date().getFullYear()
  //4年分の年号(4桁)
  yearHeaders.value = [
    currentYear, //2026
    currentYear - 1, //2025
    currentYear - 2, //2024
    `~${currentYear - 3}`, //~2023
  ]
}
// 画面に表示するための“見た目用文字列”に変換する関数(yearHeaders の値を「表に表示する用」に変換する,UI専用)
const displayYear = (y) => {
  //西暦を4桁から２桁にする
  if (typeof y === 'number') {
    return y.toString().slice(2)   // 2025 → 25
  }
  if (typeof y === 'string') {
    // "~2023" → "~23"にする
    if (y.startsWith('~') && y.length === 5) {
      return `~${y.slice(-2)}`
    }
    return y
  }
  return ''
}
/* =====================
 * 数量キャンセル判定、数量表示
 * ===================== */
const getTargetQuantity = (d) => {
  // キャンセルの場合は数量を0にする
  if ([3, 4, 5].includes(header.value?.作業状態)) {
    return 0
  }
  //そうでなければ、出荷指示数量表示
  return Number(d.出荷指示数量)
}
/* =====================
 * 指示数量の合計反映
 * ===================== */
const getTotalTargetQuantity = () => {
  //作業状態がキャンセル判定の場合は、数量を０にする
  if ([3, 4, 5].includes(header.value?.作業状態)) {
    return 0
  }
  //そうでなければ出荷指示数量合計表示
  return Number(header.value?.出荷指示数量合計||0)
}
/* ===========================
 * 明細：集荷品年号別数量　合計表示
 * =========================== */
const totalYearCounts = computed(() => {
  const realMap = {} //年号ごとの合計数量を入れる連想配列
  // details の 各行(row) を見る → その行の realYearCounts（年号別数量）を回す → 年号ごとに数量を足し込む
  details.value.forEach(row => {
    row.realYearCounts.forEach(v => {
      realMap[v.確定年号] =
        (realMap[v.確定年号] || 0) + Number(v.保存数量 || 0) //今までのその年の合計（なければ0）+ 今回の数量（数値化、なければ0）
    })
  })
  // yearHeaders に合わせて「表示用データ」を作る[例：2026 2025 2024 ~2023]
  const result = yearHeaders.value.map(y => {
    //2026, 2025, 2024
    if (typeof y === 'number') {
      return { 確定年号: y, 保存数量: realMap[y] || 0 }
    }
    //'~2023' → 2023 (2023以下の年号 を全部拾って,数量を全部足す)
    if (typeof y === 'string' && y.startsWith('~')) {
      const limit = Number(y.slice(1)) // '~2023'.slice(1)→'2023'  Number('2023')→2023
      const sum = Object.entries(realMap) //realMap を配列に変換
        .filter(([確定年号]) => Number(確定年号) <= limit)// -3年号でフィルタ
        .reduce((s, [, c]) => s + c, 0)//~2023の数量を全部足す
      return { 確定年号: y, 保存数量: sum } //例 { year: '~2023', 保存数量: 8 }
    }
    return { 確定年号: y, 保存数量: 0 }
  })
  return result
})

//「年号 → 合計数量」を “配列じゃなくて、即引けるMap形式” に変換。
const totalYearCountMap = computed(() => {
  const map = {}
  totalYearCounts.value.forEach(v => {
    map[v.確定年号] = v.保存数量
  })
  return map
})
/* =====================
 * 集荷完了判定
 * ===================== */
// 集荷完了ボタン: 集荷完了しているか判定する
const canCompletePickup = computed(() => {
  //① 明細が1行もなければ false
  if (!details.value.length) return false

  //② 全行チェック (1行でも未完了があれば false,全部OKなら true)
  return details.value.every(d => {
    //③ 年号別集荷数を合計
    const sumYearCounts = d.yearCounts.reduce(
      (sum, yc) => sum + Number(yc.保存数量 || 0), 0
    )
    //④ 数量と一致するか(true → 「集荷完了ボタン表示OK」false → 表示しない)
    return sumYearCounts === getTargetQuantity(d)
  })
})
//　数量と集荷数が一致したらグレーアウト
const isRowCompleted = (d) => {
  // 集荷数合計
  const sumYearCounts = d.yearCounts.reduce(
    (sum, yc) => sum + Number(yc.保存数量 || 0), 0
  )
  //数量
  const target = getTargetQuantity(d);
  //数量と一致するか(true → 「集荷完了ボタン表示OK」false → 表示しない)
  return sumYearCounts === target;
};
// グレーアウトにしたら、行を下に持っていく
const sortedDetails = computed(() => {
  return [...details.value].sort((a, b) => {
    const aCompleted = isRowCompleted(a)
    const bCompleted = isRowCompleted(b)
    // 未完了 → 完了 の順
    if (aCompleted !== bCompleted) {
      return aCompleted ? 1 : -1
    }
    // 同じグループ内では 出荷指示行NO asc
    return Number(a.出荷指示行NO) - Number(b.出荷指示行NO)
  })
})




/* =====================
 * 明細取得
 * ===================== */
const reLoadItems = async () => {
  loadingActive.value = true
  const loader = $loading.show()

  try {
    const key = getKeyFromUrl()
    const key2 = getKeyFromUrl2()
    if (!key) return

    const res = await axios.get('/api/pickup/detail', {
      params: { key, key2 }
    })

    header.value = res.data.出荷指示伝票 ?? null //伝票
    originNo.value = res.data.元出荷指示NO ?? null //保存された過去の出荷指示NO
    viewDetails.value = res.data.画面用表示明細 // 画面表示用明細
    rawDetails.value  = res.data.保存用明細 // 保存用明細
    //生産管理NOデータ配列
    scannedProductionNos.value = (res.data.生産管理NOs ?? []).map(v => ({
      ...v,
      数量: v.数量  // 数量カラムがなければ1とする
    }))

    // console.log('rawDetails.value:',rawDetails.value)
    // console.log('scannedProductionNos.value:',scannedProductionNos.value)

    //明細(画面上)
    details.value = (viewDetails.value ?? []).map(d => {
      // ★ APIから来た yearCounts の「実年号だけ」を抽出
      const realYearCounts = (d.yearCounts ?? [])
        .filter(v => typeof v.確定年号 === 'number')
        .map(v => ({
          確定年号: Number(v.確定年号),
          保存数量: Number(v.保存数量 || 0),
        }))

      // ★ 表示用 yearCounts を作成
      const yearCounts = yearHeaders.value.map(y => {
        // 通常年号
        if (typeof y === 'number') {
          const found = realYearCounts.find(v => v.確定年号 === y)
          return {
            確定年号: y,
            保存数量: found?.保存数量 || 0,
          }
        }
        // ~年号
        if (typeof y === 'string' && y.startsWith('~')) {
          const limit = Number(y.slice(1))
          const sum = realYearCounts
            .filter(v => v.確定年号 <= limit)
            .reduce((s, v) => s + v.保存数量, 0)

          return {
            確定年号: y,
            保存数量: sum,
          }
        }
        return { 確定年号: y, 保存数量: 0 }
      })

      return {
        ...d,
        商品CD: normalizeStr(d.商品CD),
        呼び径1: normalizeStr(d.呼び径1),
        呼び径2: normalizeStr(d.呼び径2),
        呼び径3: normalizeStr(d.呼び径3),
        realYearCounts, // ★ 唯一の正
        yearCounts,     // ★ 表示専用
      }
    })

  } catch (e) {
      console.error('取得に失敗しました', e);
  } finally {
    loader.hide()
    loadingActive.value = false
  }
}

/* ========================================================================================================================================
 * 保存用明細（HD出荷指示明細ログ、D集荷完了のDBに保存するための明細配列を作る）
 * 理由：D集荷完了に登録する際、D出荷指示明細の出荷指示行と数量を合わせるため。これがないと、HD出荷指示明細とD集荷完了のDBに保存する明細の行数が合わなくなってしまう。
 * ======================================================================================================================================= */
const saveDetails = computed(() => {
  // 最終的にDBに保存する明細配列
  const rows = []
  // 「同じ出荷指示行NO × 年号」の二重追加を防止
  const addedRows = new Map();
  // ① 画面上の「年号別集荷数」を商品単位でプールする(商品×年号ごとに合算)
  const productYearPool = {} //例：productYearPool = { "商品CD_呼び径1_呼び径2_呼び径3": {"2026": 3,"2025": 2,"~2023": 5}}
  // 元行の対応関係を作る(画面の1行が「D出荷指示明細のどの出荷指示行グループ由来か」を記録。)
  const originMap = {}

  // details.valueに「元行一覧」の項目追加 (例：元行一覧: [1, 2, 3])
  details.value.forEach(v => {
    const originKey = (v.元行一覧 || []).join(',')
    v.元行一覧?.forEach(line => {
      originMap[String(line)] = originKey
    })
  })

  // 商品×年号の残数プール（リアルタイムのデータ）
  details.value.forEach(viewRow => {
    // 元行一覧
    const originKey = (viewRow.元行一覧 || []).join(',')
    //「同一商品かどうか」の判定用キー
    const key = [ normalizeStr(viewRow.商品CD), normalizeStr(viewRow.呼び径1), normalizeStr(viewRow.呼び径2), normalizeStr(viewRow.呼び径3),originKey ].join('_')
    //商品キーの入れ物が無ければ作る
    if (!productYearPool[key]) productYearPool[key] = {} 

    // 数量
    viewRow.realYearCounts.forEach(v => {
      const qty = Number(v.保存数量) || 0 //数量
      //商品キーの中に、その年号の在庫箱が無ければ作る (例)"2025": { qty: 0, qtyChange: 減量 }
      if (!productYearPool[key][v.確定年号]) {
        productYearPool[key][v.確定年号] = {
          qty: 0, //数量
          qtyChange: viewRow.数量変化区分 // 更新対象フラグ
        }
      }
      // 年号ごとに数量加算
      productYearPool[key][v.確定年号].qty += qty
    })  
  })

  // 商品×年号ごとの「最後の行NO」を取得 (中断時の「最後の行だけ制限解除」判定に使う。) -------------
  const lastLineMap = {}
  rawDetails.value.forEach(raw => {
    //「同一商品かどうか」の判定用キー
    const key = [normalizeStr(raw.商品CD),normalizeStr(raw.呼び径1),normalizeStr(raw.呼び径2),normalizeStr(raw.呼び径3),normalizeStr(raw.指示年号)].join('_')
    // 出荷指示行NO
    const lineNo = Number(raw.出荷指示行NO)
    // 最大値更新ロジック(最大値だけ残す)
    if (!lastLineMap[key] || lastLineMap[key] < lineNo) {
      lastLineMap[key] = lineNo
    }
  })
  //-------------------------------------------------------------------------------------

  // ----------------------------------------
  // rawDetails(保存用明細)を行単位で割り当て
  // ----------------------------------------
  // 指示行ごとに割り当て　rawDetails=D出荷指示明細のデータ
  rawDetails.value
    .sort((a, b) => Number(a.出荷指示行NO) - Number(b.出荷指示行NO)) //必ず行NO順で処理
    .forEach(raw => 
  {
    // 出荷指示行NO
    const originKey = originMap[String(raw.出荷指示行NO)] || 'no-origin'
    //「同一商品かどうか」の判定用キー
    const productKey = [ normalizeStr(raw.商品CD), normalizeStr(raw.呼び径1), normalizeStr(raw.呼び径2), normalizeStr(raw.呼び径3), originKey ].join('_')
    //全体の集荷数
    const pool = productYearPool[productKey]
    //該当商品に集荷数が無ければ何もせず次へ。
    if (!pool) return
    //集荷完了:指示数量まで 中断:割り当てしない（無限大）　のときは、数量制限なしで割り当てる
    const groupKey = [ normalizeStr(raw.商品CD), normalizeStr(raw.呼び径1), normalizeStr(raw.呼び径2),  normalizeStr(raw.呼び径3), normalizeStr(raw.指示年号) ].join('_')
    const isLastLine = Number(raw.出荷指示行NO) === lastLineMap[groupKey] //商品グループの最後の行かどうか
    // 中断じゃない OR 最後の行じゃない」なら制限あり。つまり、「中断かつ最後の行」のときだけ制限なし。
    const limitByInstruction = !isSuspendSaving.value || !isLastLine
    // 出荷指示数量上限あり ? 指示数量 : 上限なし
    let remaining = limitByInstruction ? Number(raw.出荷指示数量) : Infinity
    //その出荷指示行に、今回いくつ割り当てたかの合計
    let allocatedThisRow = 0 

    // console.log('raw.出荷指示行NO:', raw.出荷指示行NO)
    // console.log('limitByInstruction（最終行判定）:', limitByInstruction)
    // console.log('remaining（残数）Infinityは残数なし:', remaining)

    //************************************************************************************ */
    // ① 指示年号を最優先で割り当てる
    const preferYear = normalizeStr(raw.指示年号)

    // console.log('pool:', pool)
    // console.log('preferYear(指示年号):', preferYear, 'pool[preferYear]:', pool[preferYear])

    // 集荷数が１以上　かつ　出荷指示数量上限が1以上　のときは割り当てる
    if (pool[preferYear]?.qty > 0 && remaining > 0) {
      const limit = limitByInstruction ? remaining : pool[preferYear].qty
      const qty = Math.min(pool[preferYear].qty, limit)

      // console.log('limit1:', limit)
      // console.log('qty1:', qty)
      // console.log('remaining1:', remaining)

      //明細行追加
      pushRow(rows, raw, preferYear, qty)

      pool[preferYear].qty -= qty //プールから割り当てた数量を減らす
      if (limitByInstruction) remaining -= qty  // 割り当て制限があるときだけ、残数から減らす
      allocatedThisRow += qty //この行に割り当てた数量を加算
    }

    // ② 余りを他年号（指示年号優先 → 他年号）
    const targetYears = [
      ...(pool[preferYear] ? [preferYear] : []), // pool[2025]:その年号の在庫がある→2025、 []:年号なし
      ...Object.keys(pool)//pool のキー（＝年号）を全部取得。
        .filter(y => y !== preferYear) //指示年号を除外　(例)preferYear = "2025"→ ["2026", "2024"]
        .sort()//年号古い順にソート (例) ["2026", "2024"]→["2024", "2026"]
    ]
    targetYears.forEach(y => {
      //年号の集荷レコードを取得　（例）{qty: 5,生産管理NO: "XXX",touched: true}
      const entry = pool[y]
      //集荷データなし、数量マイナスはスキップ
      if (!entry || entry.qty < 0) {
        console.log('異常値：集荷データなし、数量マイナスのためスキップ')
        return
      } 
      // 割り当て制限があるときは remaining を上限にする。割り当て制限がないときは、プールの数量を上限にする。
      const limit = limitByInstruction ? remaining : entry.qty

      // 再集荷ではない&上限が0以下の場合はスキップ
      if (limit <= 0 && ![6,7].includes(header.value?.作業状態)) {
        // console.log('再集荷ではない&上限が0以下の場合はスキップ')
        return
      }
      // 数量0行を作る特例：再集荷で、減量or変更なしの場合、数量0の行も作る
      const isZeroUpdate =
        [6,7].includes(header.value?.作業状態) && //再集荷
        (entry?.qtyChange == '減量' || entry?.qtyChange == '変更なし') && //数量変化区分
        entry.qty === 0 //数量0
        
        // console.log('limit2:', limit)
        // console.log('entry2:', entry)
        // console.log('isZeroUpdate2:', isZeroUpdate)
        // console.log('y2:', y, 'entry.qty2:', entry.qty)

      // qty0禁止（特例以外）
      if (entry.qty <= 0 && !isZeroUpdate) {
        // console.log('entry.qty <= 0 && !isZeroUpdate のためreturn')
        return
      }
      if (limit <= 0 && !isZeroUpdate) {
        // console.log('limit <= 0 && !isZeroUpdateのためreturn')
        return
      }

      //数量：isZeroUpdateがtrueの場合は0、そうでなければ,(割り当て制限の上限,プールの数量)の小さい方を選ぶ
      const qty = isZeroUpdate ? 0 : Math.min(entry.qty, limit)

      // console.log('addedRows.get(raw.出荷指示行NO)?.[y]):', addedRows.get(raw.出荷指示行NO)?.[y])

      //重複0行防止：[同じ出荷指示行NO&年号]の行が既に追加されているときは、数量0の行を追加しない
      if (qty === 0 && addedRows.get(raw.出荷指示行NO)?.[y]) {
        // console.log('qty === 0 && addedRows.get(raw.出荷指示行NO)?.[y])のためreturn')
        return
      }

      pushRow(rows, raw, y, qty)

      entry.qty -= qty
      if (limitByInstruction) remaining -= qty
      allocatedThisRow += qty
    })

    //************************************************************************************ */
  })
  //確認用
  // console.log('saveDetails:',rows)

  return rows //最終的にDBへ保存する明細

  // 登録する明細
  function pushRow(rows, raw, 確定年号, qty) {

    if (!addedRows.has(raw.出荷指示行NO)) addedRows.set(raw.出荷指示行NO, {})

    const rowMap = addedRows.get(raw.出荷指示行NO)
    if (!rowMap[確定年号]) rowMap[確定年号] = 0
    rowMap[確定年号] += qty

    rows.push({
      出荷指示行NO: raw.出荷指示行NO,
      商品CD: raw.商品CD,
      呼び径1: raw.呼び径1,
      呼び径2: raw.呼び径2,
      呼び径3: raw.呼び径3,
      年号: raw.指示年号,
      確定年号: 確定年号,
      数量: qty
    })
  }

  /* rows例 [{ 行NO: 1, 確定年号: 2026, 数量: 2 }, { 行NO: 1, 確定年号: 2025, 数量: 1 }, { 行NO: 2, 確定年号: 2023, 数量: 3 }]*/
})



/* スキャンで使用する関数 ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー */
/* =====================
 * スキャナ入力処理
 * ===================== */
// let buffer = ''
// let lastKeyTime = 0

// const handleScan = (e) => {
//   if (showErrModal.value) return
//   if (scanning.value) return
//   const now = Date.now() //今の時間を取得

//   console.log('速度：', e.key, now - lastKeyTime) //速度調べる

//   // 修飾キー無視
//   if (['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) return //Shiftとか混ざるとデータ壊れるから無視
//   // Enter判定(スキャナは最後にEnter送ることが多い)
//   if (e.key === 'Enter') {
//     console.log('Enter検出：', buffer)//エンター判定
//     // ★ スキャナかどうか判定（前の入力から 50ms(0.05秒)以内 → スキャナ/ それ以上 → 人間）
//     // if (now - lastKeyTime < 50 && buffer.length > 0) {
//     if (now - lastKeyTime < 100 && buffer.length > 0) {
//       e.preventDefault() //ブラウザの標準動作を止める
//       e.stopPropagation() //他のイベント処理に流さない
//       scanBarcode(buffer) //スキャナなら実行(溜めた文字列をバーコードとして処理)
//       buffer = ''
//     }
//     return
//   }
//   // 文字蓄積（高速入力なら追加）
//   // if (now - lastKeyTime < 50) {  
//   if (now - lastKeyTime < 100) {
//     buffer += e.key
//   // 遅ければリセット(人間入力は毎回リセット)
//   } else {
//     buffer = e.key
//   }
//   //最後に時間更新(最後に時間更新)
//   lastKeyTime = now
// }

// 入力部分
let buffer = '' // 1回のバーコード文字列を貯める箱
let lastKeyTime = 0 // 前のキー入力時間
const SCAN_INTERVAL = 100 // 人間 vs スキャナ判定の時間間隔（ミリ秒）
const scanQueue = [] // 待ち行列
let isProcessing = false //待ち行列

const handleScan = (e) => {
  if (showErrModal.value) return //エラーの時はスキャン処理しない
  const now = Date.now()//現在時刻
  // 修飾キー無視
  if (['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) return

  // Enterで確定
  if (e.key === 'Enter') {
    if (buffer.length > 0) {

      const code = buffer
      buffer = ''

      // キューに積む
      scanQueue.push(code)
      // console.log('キュー追加:', code, '現在件数:', scanQueue.length)

      // 処理開始
      processQueue()
    }
    return
  }

  // 文字蓄積(入力が速い → スキャナ → 文字をつなぐ, 遅い → 人間 → 文字をリセット)
  if (now - lastKeyTime < SCAN_INTERVAL) {
    buffer += e.key
  } else {
    buffer = e.key
  }
  lastKeyTime = now
}

// 処理開始トリガー
const processQueue = async () => {
  if (isProcessing) return //二重起動防止
  if (scanQueue.length === 0) return

  isProcessing = true

  //キューが空になるまで回す
  while (scanQueue.length > 0) {
    const code = scanQueue.shift() //1件取り出し(["A","B","C"] → A取り出し → ["B","C"])

    console.log('処理開始:', code)

    try {
      //1件ずつ待つ
      await scanBarcode(code)
    } catch (e) {
      console.error('処理エラー:', e)
    }
  }
  //最後に解放(次のスキャンに備える)
  isProcessing = false
}


/* ===================================
 * 同一製品グループ内で、上から順番に消化
 * =================================== */
//「この明細行（d）は、スキャンされた商品（item）と同一製品か？」を判定
function isSameProduct(a, b) {
  const fields = ['商品CD','呼び径1','呼び径2','呼び径3']
  return fields.every(f => {
    const av = normalizeStr(a?.[f])
    const bv = normalizeStr(b?.[f])
    // console.log(`${f}:`, av, '===', bv, '→', av === bv) //確認用
    return av === bv
  })
}

/* =====================
 * 未完了行を判定
 * ===================== */
const isRowAvailable = (d) => {
  //指示数量より少なければ、カウントできる
  const sum = d.yearCounts.reduce((s, yc) => s + yc.保存数量, 0)
  // console.log('sum:', sum, 'getTargetQuantity(d):', getTargetQuantity(d))
  return sum < getTargetQuantity(d)
}
/* =====================
 * 商品返却ダイヤログ
 * ===================== */
// 返却モード管理
const returnMode = ref(false)
// どの行・どの年号を返却対象にしたか
const returnTarget = ref({
  row: null, // 明細行
  yearBucket: null,   // 実際の年号
  displayYear: null   // 表示用年号
})

// 返却モーダル開く
const openReturnDialog = (row, { bucket }) => {
  // console.log('bucket:',bucket)
  isScanVerified.value = false  // 必ずリセット
  let initialYear = null

  // ★ ~2023 の場合、実際に 保存数量 がある年号を拾う
  if (typeof bucket === 'string' && bucket.startsWith('~')) {
    const limit = Number(bucket.slice(1))
    const realYears = row.realYearCounts
      .filter(v => v.確定年号 <= limit && v.保存数量 > 0)
      .sort((a, b) => a.確定年号 - b.確定年号) // ★ 年号古い順

    initialYear = realYears[0]?.確定年号 ?? null
  }
  // 通常年号
  if (typeof bucket === 'number') {
    initialYear = bucket
  }

  returnTarget.value = {
    row,//明細行
    yearBucket: bucket,//年号
    displayYear: initialYear //表示用年号
  }

  returnMode.value = true //返却フラグ
  returnItemModal.value = true //返却モーダルフラグ
}

// モーダル閉じる
const cancelReturnMode = () => {
  returnItemModal.value = false //返却モーダルフラグ
  returnMode.value = false //返却フラグ
  returnTarget.value = { row: null, 確定年号: null } //row=明細行
}

// モーダル商品情報表示
const returnProductInfo = computed(() => {
  const { row, yearBucket } = returnTarget.value

  if (!row) return null
  //呼び径表示
  const size = [row.呼び径1, row.呼び径2, row.呼び径3]
    .map(v => v?.trim())
    .filter((v, i) => v && (i === 0 || v))
    .map((v, i) => (i === 0 ? v : '×' + v))
    .join('')
  const name = (row.商品名 ?? '').trim()
  const productName = name.length > 14 ? name.slice(0, 14) + '…' : name //商品名が長くなったら省略

  return {
    商品名: productName,
    商品CD: row.商品CD,
    呼び径: size,
    年号: yearBucket,
  }
})

//返却ダイヤログ表示阻止
const isReturnableCell = (yc) => {
  //　集荷処理数が０、作業状態が2:処理済、3:キャンセル済の場合は非表示
  return yc.保存数量 > 0 && ![2, 3].includes(header.value?.作業状態)
}

/* =====================
 * 返却ダイヤログ内の年号プルダウン
 * ===================== */
const selectableYears = computed(() => {
  const { row, yearBucket } = returnTarget.value
  if (!row) return []
  // "~2023" の場合
  if (typeof yearBucket === 'string' && yearBucket.startsWith('~')) {
    const limit = Number(yearBucket.slice(1))
    return row.realYearCounts
      .filter(v => v.確定年号 <= limit && v.保存数量 > 0)
      .map(v => v.確定年号)
      .sort((a, b) => a - b) // ★古い年を先頭に
  }
  // 通常年号
  if (typeof yearBucket === 'number') {
    return [yearBucket]
  }
  return []
})

//「~2023 のときだけ表示」する判定
const isTildeYear = computed(() => {
  const y = returnTarget.value.yearBucket
  return typeof y === 'string' && y.startsWith('~')
})

//選択中年号の数量を返す
const selectedYearQuantity = computed(() => {
  const { row, displayYear, yearBucket } = returnTarget.value
  if (!row) return 0
  // ~年号のときだけ表示する想定
  if (typeof yearBucket !== 'string' || !yearBucket.startsWith('~')) {
    return 0
  }
  const y = Number(displayYear)
  if (!y) return 0
  const found = row.realYearCounts.find(v => v.確定年号 === y)
  return found?.保存数量 ?? 0
})


/* =====================
 * 指定年号判定
 * 特定の明細行（row）の、特定の年号（year）の集荷数を +1
 * ===================== */
const updateCountByRowAndYear = (row, scannedYear, scannedItem) => {
  if (isRowCompleted(row)) return
  const y = Number(scannedYear) //スキャンした年号
  if (Number.isNaN(y)) return

  // ====== ① realYearCounts を更新（唯一の正）======
  let real = row.realYearCounts.find(v => v.確定年号 === y)
  if (!real) {
    real = { 確定年号: y, 保存数量: 0}
    row.realYearCounts.push(real)
  }
  // 生産管理NO
  const productionNo = scannedItem?.生産管理NO ?? null

  //生産管理NOを配列に追加
  if (productionNo !== null) {
    const existing = scannedProductionNos.value.find(v =>
      v.生産管理NO === productionNo &&
      v.確定年号 === y &&
      normalizeStr(v.商品CD) === normalizeStr(scannedItem.商品CD) &&
      normalizeStr(v.呼び径1) === normalizeStr(scannedItem.呼び径1) &&
      normalizeStr(v.呼び径2) === normalizeStr(scannedItem.呼び径2) &&
      normalizeStr(v.呼び径3) === normalizeStr(scannedItem.呼び径3)
    )
    if (existing) {
      existing.数量++
    } else {
      scannedProductionNos.value.push({
        商品CD: scannedItem.商品CD,
        呼び径1: scannedItem.呼び径1,
        呼び径2: scannedItem.呼び径2,
        呼び径3: scannedItem.呼び径3,
        確定年号: y,
        生産管理NO: productionNo,
        数量: 1
      })
    }
  }
  //集荷数に+1
  real.保存数量++

  // ====== ② 表示用 yearCounts を再計算 ======
  row.yearCounts.forEach(yc => {
    // 通常年号
    if (typeof yc.確定年号 === 'number') {
      const f = row.realYearCounts.find(v => v.確定年号 === yc.確定年号)
      yc.保存数量 = f?.保存数量 || 0
    }
    // ~年号
    if (typeof yc.確定年号 === 'string' && yc.確定年号.startsWith('~')) {
      const limit = Number(yc.確定年号.slice(1))
      yc.保存数量 = row.realYearCounts
        .filter(v => v.確定年号 <= limit)
        .reduce((s, v) => s + v.保存数量, 0)
    }
  })
}


/* ===============================
 * 年号違う→「はい」を選択
 * ============================== */
const applyYearChange = () => {
  if (!pendingRow.value || !pendingItem.value) return //変なデータでカウントしないための保険

  //実際の「年号変更カウント処理」集荷数+1
  updateCountByRowAndYear(pendingRow.value, pendingYear.value, pendingItem.value) //(指示年号行, スキャン年号, 商品)

  //一時保存していた情報を全部クリア
  pendingRow.value = null //指示年号行
  pendingItem.value = null //商品
  pendingYear.value = null //スキャン年号
  showYearModal.value = false //年号変更モーダルフラグ
}


/* =====================
 * バーコードチェック
 * ===================== */
const scanBarcode = async (barcode) => {

  if (showErrModal.value) return   // エラー表示中は防御
  if (scanning.value) return // スキャン処理中は防御
  scanning.value = true //スキャンフラグ

  try {
    // console.log('scanBarcode-try')

    // laravelAPIでバーコード処理
    const res = await axios.post('/api/qrcodereading/check', {
      barcode, //QRコード
      出荷指示NO: header.value.出荷指示NO,
    })

    //スキャンした値
    const scanItem = res.data.スキャンデータ
    const scanYear = Number(res.data.スキャン年号)
    const scanNo = scanItem?.生産管理NO

    /* ---------------------
     *  返却モード
     * --------------------- */
    if (returnMode.value && returnTarget.value.row) {

      // 返却チェック -----------------------------------------------------
      // =========================
      // ① 商品、生産管理NOが一致するか
      // =========================
      // 画面に表示されている、返却対象として選んだ明細行
      const targetRow = returnTarget.value.row
      // 選択した年号
      const selectedYear = Number(returnTarget.value.displayYear) 

      // =========================
      // ① 商品、生産管理NOが一致するか
      // =========================
      const idx = scannedProductionNos.value.findIndex(v =>
        v.生産管理NO === scanNo &&
        v.確定年号 === selectedYear &&
        normalizeStr(v.商品CD) === normalizeStr(targetRow.商品CD) &&
        normalizeStr(v.呼び径1) === normalizeStr(targetRow.呼び径1) &&
        normalizeStr(v.呼び径2) === normalizeStr(targetRow.呼び径2) &&
        normalizeStr(v.呼び径3) === normalizeStr(targetRow.呼び径3)
      )

      // 生産管理NOが見つからない
      if (idx === -1) {
        showErrorModal('返却対象の生産管理NOが一致しません')
        return
      }

      const targetEntry = scannedProductionNos.value[idx]

      // 数量が0以下ならエラー（返却不可）
      if (targetEntry.数量 <= 0) {
        showErrorModal('返却できる数量がありません')
        return
      }
      // =========================
      // ② 実年号が返却可能か確認
      // =========================
      // ② 選択済みなら一致チェック
      if (selectedYear && selectedYear !== scanYear) {
        showErrorModal('選択された年号とスキャン年号が一致しません')
        return
      }
      // プルダウンで選択された年号が real に存在するか
      const real = targetRow.realYearCounts.find(
        v => v.確定年号 === selectedYear && v.保存数量 > 0
      )

      // realの数量チェック
      if (!real) {
        showErrorModal('返却できる数量がありません')
        return
      }

      // -1する
      real.保存数量--
      targetEntry.数量--

      // 数量が0になったら配列から削除
      if (targetEntry.数量 === 0) {
        scannedProductionNos.value.splice(idx, 1)
      }

      // -------------------------------------------------------------------


      // ★★★ 数量がなくなったらモーダル閉じる ★★★
      if (real.保存数量 <= 0) {
        returnItemModal.value = false //返却モーダルフラグ
        returnMode.value = false //返却フラグ

        //返却用明細（データ削除）
        returnTarget.value = {
          row: null,
          yearBucket: null,
          displayYear: null,
        }
      }

      // ★★★ 表示用 yearCounts を再計算 ★★★ 
      targetRow.yearCounts.forEach(yc => {
        // 通常年号
        if (typeof yc.確定年号 === 'number') {
          const f = targetRow.realYearCounts.find(v => v.確定年号 === yc.確定年号)
          yc.保存数量 = f?.保存数量 || 0
        }
        // ~年号
        if (typeof yc.確定年号 === 'string' && yc.確定年号.startsWith('~')) {
          const limit = Number(yc.確定年号.slice(1))
          yc.保存数量 = targetRow.realYearCounts
            .filter(v => v.確定年号 <= limit)
            .reduce((s, v) => s + v.保存数量, 0)
        }
      })
      return
    }


    /* ---------------------
     *  通常スキャンモード
     * --------------------- */
    //スキャンに問題なし
    if (res.data.status === 'ok') {

      // 商品一致行をすべて取得
      const matchedRows = details.value.filter(d =>
        isSameProduct(d, scanItem)
      )
      // 商品不一致エラー
      if (matchedRows.length === 0) {
        showErrorModal('製品が違います')
        return
      }
      // 未完了行の数量のみ取得
      const availableRows = matchedRows.filter(d =>isRowAvailable(d))
      // 数量オーバーエラー
      if (availableRows.length === 0) {
        showErrorModal('指定数量を超えています')
        return
      }
      // ① 商品一致 + 年号一致
      const exactYearRow = availableRows.find(d =>
        normalizeStr(d.指示年号) === normalizeStr(scanYear)
      )
      if (exactYearRow) {
        updateCountByRowAndYear(exactYearRow, scanYear, scanItem) //集荷数+1
        return
      }
      // ② 指示年号あり（年号違い）の場合、変更モーダルを開く
      const differentYearRow = availableRows.find(d => d.指示年号)
      if (differentYearRow) {
        pendingRow.value = differentYearRow //指示年号がある行
        pendingItem.value = scanItem //スキャン商品
        pendingYear.value = scanYear //スキャン年号
        showYearModal.value = true//年号変更モーダル
        return
      }
      // ③ 年号なし行
      updateCountByRowAndYear(availableRows[0], scanYear, scanItem) //集荷数+1
    }
  } catch (e) {
    console.log('scanBarcode-catch')
    const data = e.response?.data
    ElNotification({
      title: 'エラー',
      message: data?.message ?? 'バーコード読み取りの処理に失敗しました',
      type: 'error',
    })
  }
  finally {
    scanning.value = false //スキャンフラグ
  }
}


/* ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーー */



/* =====================
 * 集荷中断
//  * ===================== */
const suspendPickup = async () => {
  if (!header.value) return

  const loader = $loading.show()
  isSuspendSaving.value = true //中断フラグ

  try {
    const payload = {
      元出荷指示NO: originNo.value, //元出荷指示NO
      作業状態: header.value.作業状態,
      出荷指示NO: header.value.出荷指示NO,
      明細: saveDetails.value, // ★ 非集約DD(保存用データ)
      生産管理NOs:scannedProductionNos.value
    }

    await axios.post('/api/pickup/suspend', payload)

    ElNotification({
      title: '完了',
      message: '集荷を中断しました',
      type: 'success',
      duration: 1500,
    })

    router.push({ path: '/pickup' })//一覧に戻る

  } catch (e) {
    console.error('suspend error', e.response?.data, e)
    ElNotification({
      title: 'エラー',
      message: e.response?.data?.message ?? '中断処理に失敗しました',
      type: 'error',
    })  
  } finally {
    isSuspendSaving.value = false //中断フラグ
    loader.hide()
  }
}


/* =====================
 * 集荷・キャンセル処理完了
 * ===================== */
const completePickup = async () => {
  if (!header.value) return

  const loader = $loading.show()

  try {
    await axios.post('/api/pickup/complete', {
      元出荷指示NO: originNo.value, //元出荷指示NO
      出荷指示NO: header.value.出荷指示NO,
      作業状態: header.value.作業状態,
      明細: saveDetails.value, // ★ 非集約DD(保存用データ)
      生産管理NOs:scannedProductionNos.value
    })

    ElNotification({
      title: '完了',
      message: '集荷が完了しました',
      type: 'success',
      duration: 1500,
    })

    router.push({ path: '/pickup' })

  } catch (e) {
    ElNotification({
      title: 'エラー',
      message: e.response?.data?.message ?? '集荷完了処理に失敗しました',
      type: 'error',
    })
  } finally {
    loader.hide()
  }
}


/* =====================
 * mounted
 * ===================== */
onMounted(() => {
  buildYearHeaders()
  reLoadItems()
  document.addEventListener('keydown', handleScan)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleScan)
})
</script>



<template>
  <!-- ローディング画面 -->
  <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div>

  <section v-else-if="header" class="section dashboard">
    <div class="row">
        <ol class="breadcrumb">
            <li v-if="authItems?.[0]?.ホーム == 0">
              <router-link to="/home">ホーム</router-link>
            </li>
            <li>集荷処理</li>
            <li><router-link to="/pickup">集荷データ一覧</router-link></li>
            <li>集荷指示書</li>
        </ol>

        <!-- ヘッダー -->
        <div class="col-lg-12" v-show="!loadingActive">
            <div class="card">
                <div class="scroll-box s d header-box">
                    <table class="table_w tablesorter alter none btn_w w_border"> 
                    <thead>
                      <tr class="tb_kengen tb_product">
                        <th class="narrow_n">出荷日</th>
                        <td class="narrow_b" :class="{ error: header?.diff_ship_date }">
                          {{ formatDate(header?.出荷日) }}
                        </td>
                        <th class="narrow_n">納入先</th>
                        <td class="narrow_b" :class="{ error: header?.diff_customer_name }">
                          {{ header?.略名 || '' }}
                        </td>
                        <th class="narrow_n">出荷先<br>指示No</th>
                        <td class="narrow_b":class="{ error: header?.diff_ship_no }">{{ header?.出荷指示NO || '' }}</td>
                      </tr>
                      <tr class="tb_kengen tb_product">
                        <th class="narrow_n">貴注番</th>
                        <td class="narrow_b" :class="{ error: header?.diff_order_no }">
                          {{ header?.相手先注文NO_得意先 || '' }}
                        </td>
                        <th class="narrow_n">郵便番号</th>
                        <td class="narrow_b" :class="{ error: header?.diff_postal_code }">
                          {{ header?.郵便番号 || '' }}
                        </td>
                        <th class="narrow_n">運送会社</th>
                        <td class="narrow_b" :class="{ error: header?.diff_carrier }">
                          {{ header?.運送会社名 || '' }}
                        </td>
                      </tr>
                      <tr class="tb_kengen tb_product">
                        <th class="narrow_n">受注No</th>
                        <td class="narrow_b">
                          {{ header?.受注_移動NO || '' }}
                        </td>
                        <th class="narrow_n">納入先<br>住所</th>
                        <td class="narrow_b" :class="{ error: header?.diff_address1 || header?.diff_address2 }">
                          {{ (header?.住所1 || '') + (header?.住所2 || '') }}

                        </td>
                        <th class="narrow_n">出荷形態</th>
                        <td class="narrow_b" :class="{ error: header?.diff_shipping_type }">
                          {{ getShippingTypeName(header?.出荷形態) }}
                        </td>
                      </tr>
                    </thead>
                  </table>
                </div>
            </div>
        </div>

        <!-- ===== 営業伝言 ===== -->
        <div class="col-lg-12" v-show="!loadingActive">
            <table class="memo-table">
              <tr>
                <th>営業伝言</th>
                <td>
                  {{ header?.備考|| '' }}
                </td>
              </tr>
            </table>
        </div>

        <!-- 集荷情報一覧 -->
        <div class="col-lg-12" v-show="!loadingActive">
          <div class="card">
              <div class="scroll-box s scroll-box_y d">
                  <table class="table_w tablesorter alter none btn_w sp_w w_border table_c" id="table_sort"> 
                    <thead>
                      <tr class="tb_kengen tb_product">
                        <th class="narrow_e" rowspan="2">No.<i class="fa-solid fa-sort"></i></th>     
                        <th class="narrow_f" rowspan="2">商品名</th>     
                        <th class="narrow_e" rowspan="2">商品CD</th>
                        <th class="narrow_e" rowspan="2">呼び径</th>     
                        <th class="narrow_e" rowspan="2">数量</th>
                        <th class="narrow_e" rowspan="2">単位</th>
                        <th class="narrow_e" rowspan="2">重量(kg)</th>         
                        <th class="narrow_e" rowspan="2">梱口</th>
                        <th class="narrow_l" rowspan="2">指示<br>年号</th>
                        <th class="narrow_f" colspan="4">集荷品年号</th>
                        <th class="narrow_m" rowspan="2">保管場所</th>
                      </tr>
                      <!-- 年号4年分 -->
                      <tr class="tb_kengen tb_product">
                        <th v-for="y in yearHeaders" :key="y"> {{ displayYear(y) }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- 明細行 -->
                      <tr v-for="(d, index) in sortedDetails" :key="d.表示行NO" 
                          :class="{ 
                            error: header?.作業状態 == 5 || ([6,7].includes(header?.作業状態) && d.数量変化区分 == '減量'), // 【作業状態】 5:未処理のキャンセル, 6:変更
                            grayout: isRowCompleted(d) || ((header?.作業状態 == 2) && d.数量変化区分 == '変化なし') || [3,4].includes(header?.作業状態) // 【作業状態】 2:処理済, 3:処理済みキャンセル, 4:未集荷のキャンセル
                          }"
                      >
                        <td>{{ index + 1 }}</td>
                        <td>{{ d.商品名 }}</td>  
                        <td>{{ d.商品CD }}</td>  
                        <td>
                            {{
                              [
                                d.呼び径1?.trim(),
                                d.呼び径2?.trim() ? '×' + d.呼び径2.trim() : '',
                                d.呼び径3?.trim() ? '×' + d.呼び径3.trim() : ''
                              ].filter(Boolean).join('')
                            }}
                        </td>
                        <!-- 数量 -->
                        <td>{{ getTargetQuantity(d) }}</td>  
                        <td>{{ d.単位 }}</td>  
                        <td>{{ d.重量 }}</td>  
                        <td>{{ d.出荷指示梱数 }}</td>  
                        <td>{{ d.指示年号 ? d.指示年号 : '' }}</td>  
                        <td
                          v-for="yc in d.yearCounts"
                          :key="yc.確定年号"
                          class="ocr_btn"
                          @click.prevent="
                            isReturnableCell(yc) && openReturnDialog(d, {
                              bucket: yc.確定年号,          // ~2023
                              realYear: null            // まだ未確定
                            })
                          "
                        >
                          {{ header?.作業状態 === 3 ? '' : (yc.保存数量 === 0 ? '' : yc.保存数量) }}
                        </td>
                        <!-- <td>{{ d.保管場所名 }}, 数量変化区分:{{ d.数量変化区分 }}</td> -->
                        <td>{{ d.保管場所名 }}</td>
                      </tr>

                      <!-- 合計値 -->
                      <tr class="tb_kengen tb_product tb_total">
                        <td colspan="3" class="table_none"></td>
                        <th>合計</th>
                        <td>{{ getTotalTargetQuantity() }}</td>
                        <td class="table_none"></td>
                        <td>{{ header?.重量合計 || 0 }}</td>
                        <td>{{ header?.出荷指示梱数合計 || 0 }}</td>
                        <td  class="table_none"></td>
                        <!-- 年号別集荷数の合計 -->
                        <td v-for="(y, i) in yearHeaders" :key="i" class="ocr_btn">
                            {{
                              header?.作業状態 === 3
                                ? ''
                                : (totalYearCountMap[y] || '')
                            }}
                        </td>
                        <td class="table_none"></td>
                      </tr>
                    </tbody>               
                </table>
              </div>
          </div><!-- card -->
          
          <!-- ===== 集荷中断ボタン ===== -->
          <div class="col-sp-12 ma_top_a line_up flex">
            <!-- 戻る -->
            <a href="#" v-if="[2, 3, 4].includes(header?.作業状態)" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
            <!-- 集荷中断 -->
            <button v-else class="button_i" @click.prevent="suspendPickup">集荷中断</button>
              <!-- 集荷完了（全件OK時のみ表示） -->
            <button v-if="canCompletePickup && [0,1,5,6,7].includes(header?.作業状態)" class="button_i c" @click.prevent="completePickup">集荷完了</button>
          </div>
          <!-- 確認用 -->
          <!-- <p>作業状態：{{ header?.作業状態 }}</p> -->

          <!-- ======= 変号変更ポップアップ ======= -->
            <div class="modal_wrap" id="delete_modal" v-if="showYearModal">
              <div class="modal_inner s">
                <div class="signup_form">
                  <h5>指示と製品の年号が違っています。<br>
                    データを変更しますか？</h5>
                </div>
                <div class="btn_space_modal">
                  <div class="submit_btn_yes id_btn_yes" @click="applyYearChange">はい</div>
                  <div class="submit_btn_no close_icon" @click="showYearModal = false">いいえ</div>
                </div>          
            </div>
          </div>
          <!-- ============================ -->
          <!-- ======= エラーポップアップ ======= -->
          <div class="modal_wrap err_modal" id="ocr_modal" v-if="showErrModal">
              <div class="modal_inner s">
                  <div class="signup_form">
                  <h5>{{ err_msg }}</h5>
                  </div>
                  <div class="btn_space_modal">
                  <div class="submit_btn_no close_icon" @click="closeErrModal">確認</div>
                  </div>          
              </div>
          </div> 
          <!-- ============================ -->
          <!-- ======= 返却ダイアログ======= -->
          <div class="modal_wrap search-ocr" v-if="returnItemModal">
            <div class="modal_inner s">
              <div class="signup_form">
                <h5>
                  商品を返却します。<br>
                  QRコードを読み込んでください<br><br>
                  <div class="return-product-wrap" v-if="returnProductInfo">
                    <div class="return-product-box">
                      <div class="row">
                        <span class="label">商品名</span>
                        <span class="value">{{ returnProductInfo.商品名 }}</span>
                      </div>
                      <div class="row">
                        <span class="label">商品CD</span>
                        <span class="value">{{ returnProductInfo.商品CD }}</span>
                      </div>
                      <div class="row">
                        <span class="label">呼び径</span>
                        <span class="value">{{ returnProductInfo.呼び径 }}</span>
                      </div>
                      <div class="row">
                        <span class="label">年号</span>
                        <span class="value">{{ returnProductInfo.年号 }}</span>
                      </div>
                      <!-- ~〇〇年の時だけ表示 -------->
                        <div v-if="isTildeYear">
                        <div class="row">
                          <span class="label">返却年号</span>
                          <span class="value">                            
                            <select v-model="returnTarget.displayYear" :disabled="selectableYears.length === 0">
                              <option v-for="y in selectableYears" :key="y" :value="y">{{ y }}</option>
                            </select>
                          </span>
                        </div>
                        <div class="row">
                          <span class="label">数量</span>
                          <span class="value">{{ selectedYearQuantity }}</span>
                        </div>
                        </div>
                      <!-- ------------------ -->
                    </div>
                  </div>
                </h5>
              </div>
              <div class="btn_space_modal">
                <div class="submit_btn_no" @click="cancelReturnMode">キャンセル</div>
              </div>
            </div>
          </div>
          <!-- ============================ -->
      </div>
    </div>
  </section>
  <p v-else>データがありません。</p>
</template>

<style scoped>
.loading-wrap {
  position: fixed;
  top: 50%;
  left: 50%;
  width: 15vw;
  height: 15vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255, 255, 255, 0.5);
  z-index: 2;
  font-size: 1.5em;
  transform: translate(-50%, -50%);
}
/* Tablesorter がつける div に適用する */
#table_sort th .tablesorter-header-inner {
  width: 100%;
  display: block;
  text-align: center !important;
}
/* 縦スクロール（10行制限） */
.scroll-box {
  max-height: calc(10 * 45px + 1px);
}
.line_up button,.line_up a,.disp_btn {
  margin: 10px 60px 0 0 !important;
}
.card {
  margin-bottom: 0px;
}
@media screen and (max-width: 1200px) {
  .scroll-box {
    max-height: calc(10 * 26px + 1px);
  }
}
@media screen and (min-width: 591px) {
  .table_w  {
    min-width: 680px;
  }
}
@media screen and (max-width: 590px) {
    .table_w {
        min-width: 900px;
    }
}
</style>

