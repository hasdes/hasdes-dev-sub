<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount, nextTick } from 'vue' //Vue 3 の Composition API の機能。
import { useRouter } from 'vue-router' //Vue Router の関数で、画面遷移を行うために使います
import dayjs from 'dayjs'//日付操作用ライブラリ
import axios from 'axios'//API通信


// 配車設定（権限）を親コンポーネント(Layout.vue)から受け取る
const props = defineProps({
  authItems: {
    type: Array, // authItems は配列で
    required: true // 絶対に親から渡してもらわないとダメ
  }
})

const router = useRouter() //画面遷移用


// セッション切れ（401）時は自動でログアウトにリダイレクトする*********************
const secureAxios = {
  async get(url, config = {}) {
    try {
      return await axios.get(url, config)
    } catch (error) {
      if (error.response?.status === 401) router.push('auth/logout')      
      throw error
    }
  },

  async post(url, data = {}, config = {}) {
    try {
      return await axios.post(url, data, config)
    } catch (error) {
      if (error.response?.status === 401) router.push('auth/logout')
      throw error    
    }
  }
}
//************************************************************************/


// === 状態管理 ===
//日付関連
const currentDate = ref(dayjs()) // 現在の日付
const currentYear = computed(() => currentDate.value.year()) // 現在の年
const currentMonth = computed(() => currentDate.value.month() + 1) // 現在の月

//セッション
const tantoCd = ref(null) // 担当者CD（セッションなどから取得）

// データ格納（日付ごと）
const shipmentData = ref({})//D配車計画データ
const notPossibleData = ref({})//配車不可データ
const departmentName = ref('')//拠点名
const targetBumonCd = ref('')//部門CD

// ローディングとエラー状態の管理(API通信中の状態やエラー表示用。)
const loadingActive = ref(false)
const errorMessage = ref('')

//配車不可
const dispatchDisplay = ref(0)
//=================


// === カレンダー日付リスト ===
const dates = computed(() => {
  const start = currentDate.value.startOf('month')
  const days = currentDate.value.endOf('month').date()
  const offset = start.day()
  return [...Array(offset).fill(null), ...Array.from({ length: days }, (_, i) => i + 1)]
})



// 出荷予定データを日付ごとにまとめる関数
const groupShipmentsByDate = (shipments) => {
  const grouped = shipments.reduce((acc, row) => {
    const key = dayjs(row.出荷予定日, 'YYYYMMDD').format('YYYYMMDD')
    ;(acc[key] ||= []).push(row)
    return acc
  }, {})

  // ソート（車両 → 積み下ろし順 の昇順）
  for (const date in grouped) {
    grouped[date].sort((a, b) => {
      const parse = (str) => {
        if (!str || typeof str !== 'string') return [Infinity, Infinity]
        const parts = str.split('-').map(s => Number(s.trim()))
        return [parts[0] || Infinity, parts[1] || Infinity]
      }

      const [a車両, a順] = parse(a.車両 + '-' + a.積み下ろし順)
      const [b車両, b順] = parse(b.車両 + '-' + b.積み下ろし順)

      if (a車両 !== b車両) return a車両 - b車両
      return a順 - b順
    })
  }

  return grouped
}


// === API取得処理(統合されたデータ取得関数) ===
const fetchAllData = async () => {
  loadingActive.value = true

  try {
    const start = currentDate.value.startOf('month').format('YYYY-MM-DD')
    const end = currentDate.value.endOf('month').format('YYYY-MM-DD')

    // 2つのAPIを並行実行
    const [shipmentsRes, notPossibleRes] = await Promise.all([
      secureAxios.get('/api/dispatch/list', { params: { start_date: start, end_date: end } }),
      secureAxios.get('/api/dispatch-unavailable/list', { params: { start_date: start, end_date: end } })
    ])

    // データの処理
    shipmentData.value = groupShipmentsByDate(shipmentsRes.data.shipments || [])
    notPossibleData.value = groupShipmentsByDate(notPossibleRes.data || [])
    departmentName.value = shipmentsRes.data.departmentName || '不明'
    targetBumonCd.value = shipmentsRes.data.targetBumonCd || 1

  } catch (error) {
    console.error('データ取得に失敗:', error)
    errorMessage.value = 'データの取得に失敗しました'
    // エラー時は空のデータを設定
    shipmentData.value = {}
    notPossibleData.value = {}
  } finally {
    // DOM更新を待ってからローディング非表示
    await nextTick()
    loadingActive.value = false
  }
}


// === 月変更（デバウンス）(月変更時などの急連打に対応するため、300ms待機してからデータ再取得。) ===
let debounceTimer = null
const debouncedFetchData = () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    fetchAllData()
  }, 300)
}


// 月変更時の処理
watch(currentDate, () => {
  if (tantoCd.value) {
    localStorage.setItem(`selectedMonth_${tantoCd.value}`, currentDate.value.format('YYYY-MM-DD'))
  }
  debouncedFetchData()
})


// コンポーネントが破棄される時の処理
onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})

// === 月の前後へ ===
// 前月へ
const goToPrevMonth = () => currentDate.value = currentDate.value.subtract(1, 'month')

// 次月へ 2ヶ月先まで
const goToNextMonth = () => {
  const next = currentDate.value.add(1, 'month').startOf('month')
  if (!next.isAfter(dayjs().add(2, 'month').startOf('month')))
    currentDate.value = next
}

//2ヶ月先に到達していたら矢印ボタンを非表示にする
const isNextMonthDisabled = computed(() => {
  return currentDate.value.add(1, 'month').startOf('month').isAfter(dayjs().add(2, 'month').startOf('month'))
})
// const isPrevMonthDisabled = computed(() => false)// 常に遷移可能に


// === その他機能 ===
//市町村区だけ抽出
// const extractCityWard = (address) => {
//   const match = address?.match(/(?:都|道|府|県)([^市区町村]+市|[^市区町村]+区|[^市区町村]+町|[^市区町村]+村)/)
//   return match ? match[1] : address || ''
// }

// ================================================
// 特殊市（市が名前に含まれる市名）
// ================================================
const citiesWithCityInName = [
  "野々市市", "四日市市", "廿日市市", "十日町市", "大町市", "村山市", "田村市", "東村山市", "武蔵村山市", "羽村市", "村上市", "大村市", "郡山市", "蒲郡市", "小郡市", "大和郡山市", "郡上市"
];

// ================================================
// 郡＋町・村 の辞書
// ================================================
const gunChoSonList = [
  "吉野郡下市町", "杵島郡大町町", "柴田郡村田町", "佐波郡玉村町", "中新川郡上市町", "西八代郡市川三郷町", "芳賀郡市貝町", "余市郡余市町", "赤穂郡上郡町", "田村郡", "北村山郡", "西村山郡", "東村山郡",
];

// ================================================
// 市区町村抽出（完全版）
// ================================================
const extractCityWard = (address = "") => {
  if (!address) return "";

  const afterPref = address.replace(/.*?(都|道|府|県)/, "");

  // ① 辞書：郡＋町/村
  for (const item of gunChoSonList) {
    if (afterPref.startsWith(item)) return item;
  }

  // ② 辞書：特殊市
  for (const city of citiesWithCityInName) {
    if (afterPref.startsWith(city)) return city;
  }

  // ③ 市＋区（例：名古屋市中村区）
  const cityWard = afterPref.match(/(.+?市.+?区)/);
  if (cityWard) return cityWard[1];

  // ④ 郡＋市（田村郡田村市 → 田村市）
  const gunShi = afterPref.match(/.+?郡(.+?市)/);
  if (gunShi) return gunShi[1];

  // ⑤ 郡だけ
  if (/^.+?郡$/.test(afterPref)) return afterPref;

  // ⑥ 正規表現 fallback
  const gunChoSon = afterPref.match(/(.+?郡.+?[町村])/);
  if (gunChoSon) return gunChoSon[1];

  const city = afterPref.match(/(.+?市)/);
  if (city) return city[1];

  const ward = afterPref.match(/(.+?区)/);
  if (ward) return ward[1];

  const town = afterPref.match(/(.+?町)/);
  if (town) return town[1];

  const village = afterPref.match(/(.+?村)/);
  if (village) return village[1];

  return "";
};


// ===  表示用に日付をフォーマット ===
const formatDate = (day) => currentDate.value.startOf('month').date(day).format('YYYYMMDD')

// ===  拠点変更 ===
const changeBase = () => {
  if (!loadingActive.value) router.push({ name: 'dispatch.filter', query: { bumonCd: targetBumonCd.value } })
}


// === モーダル関連 ===
import Edit from '@/Pages/Dispatch/Edit.vue'
import Add from '@/Pages/Dispatch/Add.vue'
import Delete from '@/Pages/Dispatch/Delete.vue'
const editRef = ref()
const addRef = ref()
const DeleteRef = ref()

//配車不可にする
const handleUnavailableClick = (date) => {
  if (!loadingActive.value) addRef.value?.open(formatDate(date))
}

//配車可にする
const handleAvailableClick = (id) => {
  if (!loadingActive.value && id && dispatchDisplay.value == 0) DeleteRef.value?.open(id)
}


// === 初期化 ===
onMounted(async () => {
  try {
    const res = await secureAxios.get('/api/session-check', { withCredentials: true })
    tantoCd.value = res.data.担当者CD
    const saved = localStorage.getItem(`selectedMonth_${tantoCd.value}`)
    if (saved) currentDate.value = dayjs(saved)
    await fetchAllData()
  } catch (e) {
    errorMessage.value = 'セッション情報の取得に失敗しました'
  }
})

// watch(() => props.items, (items) => {
//   if (items?.length > 0) dispatchDisplay.value = items[0]['配車可_不可'] ?? 1
// }, { immediate: true })
watch(() => props.authItems, (authItems) => {
  if (authItems?.length > 0) dispatchDisplay.value = authItems[0]['配車可_不可'] ?? 1
}, { immediate: true })



</script>

<template>
<section class="section dashboard">
    <!-- ★ 追加：ローディングオーバーレイ -->
    <div v-if="loadingActive" class="loading-wrap">
      <span>読み込み中...</span>
    </div>

    <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>情報表示</li>
        <li>直送配車計画</li>
    </ol>

    <div class="dis_flex">
        <button class="button_r dis_btn" @click="changeBase()">拠点</button>
    </div>
    <div class="dis_txt">
        <p class="dis_name">{{ departmentName }}</p>
    </div>

    <div class="container_cr scroll-box" :class="{ 'loading-blur': loadingActive }">
        <div class="c-calender">
            <div class="c-calender__header">
                <a href="#" @click.prevent="goToPrevMonth"><i class="fa-solid fa-arrow-left"></i></a>            
                <p>{{ currentYear }}年{{ currentMonth }}月</p>
                <a href="#" @click.prevent="goToNextMonth" v-if="!isNextMonthDisabled"><i class="fa-solid fa-arrow-right"></i></a>             
            </div>      
            <div class="c-calender__day grid">
                <span class="sun">日</span>
                <span>月</span>
                <span>火</span>
                <span>水</span>
                <span>木</span>
                <span>金</span>
                <span class="sat">土</span>
            </div>
            <div class="c-calender__date grid">
                <span v-for="(date, index) in dates" :key="index" 
                  :style="notPossibleData[formatDate(date)] ? 'background-color: rgb(177, 177, 177);' : ''">
                    <template v-if="date">

                      <div class="date-header">
                        <!-- 日付 -->
                        {{ date }}
                        <!-- 配車不可ボタン -->
                          <button
                            v-if="notPossibleData[formatDate(date)]"
                            class="not_btn g"
                            @click.prevent="handleAvailableClick(notPossibleData[formatDate(date)]?.[0]?.HD配車不可_ID)"
                          >配車不可</button>

                          <div
                            v-else-if="dispatchDisplay == 0"
                            class="not_btn delete_btn"
                            @click.prevent="handleUnavailableClick(date)"
                          >配車不可</div>
                      </div>

                      <!-- 1日あたりの合計重量を表示 -->
                      <div>
                        <ol v-for="group in shipmentData[formatDate(date)] || []"
                            :key="group.出荷予定日 + '_' + group.出荷先CD"
                            class="mark_txt kengen_btn"
                            @click.prevent="editRef.open(group)">
                          <li>
                            <template v-if="group.車両 || group.積み下ろし順">
                              {{ group.車両 }}-{{ group.積み下ろし順 }}
                            </template>

                            {{ extractCityWard(group.出荷先住所1).length > 10
                                ? extractCityWard(group.出荷先住所1).slice(0, 10) + '…'
                                : extractCityWard(group.出荷先住所1)
                            }}
                            {{ Math.ceil(group.items.reduce((sum, i) => sum + Number(i.重量), 0)).toLocaleString() }}㌔
                          </li>
                        </ol>
                      </div>

                    </template>
                    <template v-else>
                    </template>
                </span>

            </div>
        </div>
    </div>    

    <!-- <Edit ref="editRef"@reLoad="reLoadItems"></Edit> -->
    <!-- <Edit ref="editRef" :items="props.items" /> -->
    <Edit ref="editRef" :items="props.authItems" />
    <Add ref="addRef" @reLoad="reLoadItems"></Add>
    <Delete ref="DeleteRef" @reLoad="reLoadItems"/>
  
</section>
</template>

<style>

.loading-wrap {
  position: fixed;
  top: 50%;
  left: 50%;
  width: 15vw;
  height: 15vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255,255,255,0.7);
  z-index: 2000;           /* 既存要素より上に置く */
  font-size: 1.5rem;
  transform: translate(-50%, -50%);
}

/* ======== レスポンシブクラス・装飾系 ======== */
@media screen and (max-width: 500px) {
  .red-line-mobile {
    border-bottom: 3px solid rgb(0, 0, 0) !important;
  }
}

/* ======== ローディング画面 ======== */
.loading-blur {
  opacity: 0.5;
  /* pointer-events: none; */
}

/* ======== カレンダーセル ======== */
.c-calender__date span {
  height: auto;
  min-height: 100px; /* 初期高さ */
  font-size: 14px;
  display: grid;
  grid-template-rows: auto 1fr auto;
  gap: 4px;
}


/* モバイルでは高さに制限をかけ、aspect-ratioを解除 */
@media screen and (max-width: 768px) {
  .c-calender__date span {
    aspect-ratio: unset;
    min-height: 140px;
  }
}

/* ======== カレンダーセルの内部構造 ======== */
.cell-date {
  font-weight: 700;
  font-size: 0.9rem;
}

.cell-btn {
  text-align: center;
}


/* ======== 配車不可ボタン（.not_btn） ======== */
.not_btn {
  font-size: 15px;
  padding: 7px 10px;
  width: 90px;
}

@media screen and (max-width: 1440px) {
  .not_btn {
    font-size: 14px;
    padding: 5px 9px;
    width: 85px;
  }

  .mark_txt {
    font-size: 11px;
  }

}

@media screen and (max-width: 1024px) {
  .not_btn {
    font-size: 13px;
    padding: 5px 8px;
    width: 80px;
  }

  .mark_txt {
    font-size: 10px;
  }
}

@media screen and (max-width: 768px) {
  .not_btn {
    font-size: 12px;
    padding: 4px 7px;
    width: 70px;
  }

  .mark_txt {
    font-size: 9px;
  }
}

@media screen and (max-width: 480px) {
  .not_btn {
    font-size: 11px;
    padding: 3px 6px;
    width: 65px;
  }
}

/* ======== 日付と配車不可ボタンの配置 ======== */

.date-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

/* 上書きして中央固定を解除 */
.date-header .not_btn {
  position: static !important;
  transform: none !important;
  width: auto;
  font-size: 0.9rem;
  padding: 4px 8px;
  white-space: nowrap;
}



</style>
