<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import dayjs from 'dayjs'; //日付を扱うライブラリ
import axios from 'axios';

// セッション確認
// axios.get('/api/session-check', { withCredentials: true })
//   .then(res => {
//     // if (res.data.authenticated && res.data.has_tanto_cd) {
//     if (res.data.authenticated) {
//       console.log('ログイン済み：', res.data.user);
//       dispatchDisplay.value = res.data.dispatch_display ?? 1;  // 配車不可
//       console.log('配車表示セッション:', dispatchDisplay.value);
//     } else {
//       console.log('セッション切れまたは未ログイン');
//       router.push('/elogin');// ログインページへ遷移
//     }
// });

//拠点セッション確認
// onMounted(async () => {
//   try {
//     const response = await axios.get('/api/get-department-cd')
//     // console.log('保存された拠点のセッション:', response.data.部門CD)
//   } catch (error) {
//     console.error('拠点セッションの取得に失敗しました:', error)
//   }
// })

//配車設定（権限）をLayout.vueから受け取る
const props = defineProps({
  items: {
    type: Array,
    required: true
  }
})
// console.log('受け取った items:', props.items)


const router = useRouter();

const currentDate = ref(dayjs()); // 現在の日付
const tantoCd = ref(null); // 担当者CD（セッションなどから取得）

// 表示する年月
const currentYear = computed(() => currentDate.value.year()); // currentDate から「西暦の年（2025など）」だけを取り出します。
const currentMonth = computed(() => currentDate.value.month() + 1); // month() は0始まりのため、+1が必要。

// カレンダーに表示する日付リスト（先頭の空白付き）
const dates = computed(() => {
  const start = currentDate.value.startOf('month')
  const end = currentDate.value.endOf('month')
  const startDay = start.day()
  const daysInMonth = end.date()
  const result = []

  for (let i = 0; i < startDay; i++) result.push(null)
  for (let i = 1; i <= daysInMonth; i++) result.push(i)

  return result
})


// 出荷予定データ格納（日付ごと）
const shipmentData = ref({})
const notPossibleData = ref({})
const departmentName = ref(''); // 部門名を格納する変数
const targetBumonCd = ref(''); // 部門名を格納する変数

// APIレスポンス配列を日付ごとにグループ化する関数
const groupShipmentsByDate = (shipments) => {
  return shipments.reduce((acc, group) => {
    const formattedDate = dayjs(group.出荷予定日, 'YYYYMMDD').format('YYYYMMDD');
    if (!acc[formattedDate]) acc[formattedDate] = []
    acc[formattedDate].push(group) // 1グループ = 同一日同一出荷先のitems
    return acc
  }, {})
}


// 出荷予定データ取得
const fetchShipments = async () => {
//   console.log('fetchShipments() 呼び出し確認')

  //取得期間
  const start = currentDate.value.startOf('month').format('YYYY-MM-DD')
  const end = currentDate.value.endOf('month').format('YYYY-MM-DD')

  // console.log('取得期間:', start, '〜', end); // 日付確認

  try {
    const res = await axios.get('/api/dispatch/list', {
      params: { start_date: start, end_date: end }
    })
    // console.log('APIレスポンス:', res.data) // ← ここで中身を確認
    shipmentData.value = groupShipmentsByDate(res.data.shipments)//出荷予定日ごとにグループ化
    departmentName.value = res.data.departmentName || '不明'//拠点名    
    targetBumonCd.value = res.data.targetBumonCd || 1//部門CD   
  } catch (e) {
    console.error('出荷予定データの取得に失敗:', e)
  }
}

// 配車不可データ取得
const fetchNotPossible = async () => {
  //console.log('fetchShipments() 呼び出し確認')

  //取得期間
  const start = currentDate.value.startOf('month').format('YYYY-MM-DD')
  const end = currentDate.value.endOf('month').format('YYYY-MM-DD')

  try {
    const res = await axios.get('/api/dispatch-unavailable/list', {
      params: { start_date: start, end_date: end }
    })
    // console.log('配車不可データ:', res.data) // ← ここで中身を確認
    notPossibleData.value = groupShipmentsByDate(res.data)//出荷予定日ごとにグループ化
    // console.log(res.data.notPossibleData);
    
  } catch (e) {
    console.error('配車不可データの取得に失敗:', e)
  }
}

//市町村区だけ抽出
const extractCityWard = (address) => {
  const match = address.match(/(?:都|道|府|県)([^市区町村]+市|[^市区町村]+区|[^市区町村]+町|[^市区町村]+村)/);
  return match ? match[1] : '';
}

// 表示用に日付をフォーマット（例: "2025-05-01"）
const formatDate = (day) => {
    // return currentDate.value.date(day).format('YYYYMMDD'); // ← ハイフンなしに変更
    return currentDate.value.startOf('month').date(day).format('YYYYMMDD');
}


// 前月へ
const goToPrevMonth = () => {
  currentDate.value = currentDate.value.subtract(1, 'month')
}

// 次月へ 2ヶ月先まで
const goToNextMonth = () => {
  const maxMonth = dayjs().add(2, 'month').startOf('month') // 今月含めて2ヶ月先まで
  const nextMonth = currentDate.value.add(1, 'month').startOf('month')

  if (nextMonth.isAfter(maxMonth)) {
    return // 2ヶ月先を超える遷移はキャンセル
  }

  currentDate.value = nextMonth
}

//2ヶ月先に到達していたら矢印ボタンを非表示にする
const isNextMonthDisabled = computed(() => {
  const maxMonth = dayjs().add(2, 'month').startOf('month');//現在の日時に2ヶ月を足す
  const nextMonth = currentDate.value.add(1, 'month').startOf('month');//今表示中の年月の翌月の同じ日付
  return nextMonth.isAfter(maxMonth);//nextMonth が maxMonth より後の日付かどうか？をチェック
});



//拠点変更
// function changeBase() {
//   router.push({ name: 'dispatch.filter' })
// }
function changeBase() {
  router.push({
    name: 'dispatch.filter',
    query: {
      bumonCd: targetBumonCd.value //クエリに部門CDを追加
    }
  })
}

//編集
import Edit from '@/Pages/Dispatch/Edit.vue'
const editRef = ref();// 空の ref を定義

//配車設定（権限）の取得
const dispatchDisplay = ref(0)
watch(() => props.items, (newItems) => {
  if (newItems.length > 0) {
    const setting = newItems[0]["配車設定"];
    dispatchDisplay.value = setting;
    // console.log("配車設定:", setting);
    // console.log("dispatchDisplay:", dispatchDisplay.value);
  }
}, { immediate: true }); // マウント直後にも実行


//配車不可にする
import Add from '@/Pages/Dispatch/Add.vue'
const addRef = ref();
// 配車不可ボタンクリック時の処理
const handleUnavailableClick = (date) => {
  const formattedDate = formatDate(date);
  addRef.value.open(formattedDate);
}

//配車可にする
import Delete from '@/Pages/Dispatch/Delete.vue'
const DeleteRef = ref();
// 配車可ボタンクリック時の処理
const handleAvailableClick = (id) => {
  if (!id) {
    console.warn("D配車不可_ID が見つかりませんでした。");
    return;
  }
  //権限があれば表示
  if(dispatchDisplay.value == 0) {
    // console.log(id);
    DeleteRef.value.open(id);
  }
};



// // 初回読み込み
// onMounted(() => {
//   fetchShipments()
//   fetchNotPossible()
// })

// // 月変更時にも再取得
// watch(currentDate, () => {
//   fetchShipments()
//   fetchNotPossible()
// })



// 初回読み込み・担当者CDを使ったキーで保存・読み込み
onMounted(async () => {
  try {
    // 担当者CDをAPIで取得（例）
    const res = await axios.get('/api/session-check', { withCredentials: true });
    tantoCd.value = res.data.担当者CD;//セッションから担当者CDを取得
    // console.log("担当者CD:", tantoCd.value);

    // localStorage から保存済みの年月を取得（ユーザーごと）
    const savedDate = localStorage.getItem(`selectedMonth_${tantoCd.value}`);
    if (savedDate) {
      currentDate.value = dayjs(savedDate);
    }
    // console.log("年月:", currentDate.value);

    // 初回データ取得
    fetchShipments();
    fetchNotPossible();
  } catch (e) {
    console.error('担当者CDの取得に失敗', e);
  }
});


//月を変更したとき、保存する
watch(currentDate, (newDate) => {
  if (tantoCd.value) {
    localStorage.setItem(`selectedMonth_${tantoCd.value}`, newDate.format('YYYY-MM-DD'));
  }
  fetchShipments();
  fetchNotPossible();
});


</script>


<template>
<section class="section dashboard">

    <ol class="breadcrumb">
        <li><router-link to="/home">ホーム</router-link></li>
        <li>情報表示</li>
        <li>直送配車計画</li>
    </ol>

    <div class="dis_flex">
        <button class="button_r dis_btn" @click="changeBase()">拠点</button>
    </div>
    <div class="dis_txt">
        <p class="dis_name">{{ departmentName }}</p>
    </div>

    <div class="container_cr scroll-box">
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
                <span v-for="(date, index) in dates" :key="index" :style="notPossibleData[formatDate(date)] ? 'background-color: rgb(177, 177, 177);' : ''">
                    <template v-if="date">
                        {{ date }}
                        <!-- 1日あたりの合計重量を表示 -->
                        <ol v-for="group in shipmentData[formatDate(date)] || []"
                            :key="group.出荷予定日 + '_' + group.出荷先CD"
                            class="mark_txt kengen_btn"
                            @click.prevent="editRef.open(group)">
                          <li>
                            {{ group.items[0].車両 }} - {{ group.items[0].積み下ろし順 }} 
                            {{ extractCityWard(group.出荷先住所1) }}
                            {{ group.items.reduce((sum, i) => sum + Number(i.重量), 0) }}㌔
                          </li>
                        </ol>
                        <!-- 配車不可のデータがある場合 -->
                        <template v-if="notPossibleData[formatDate(date)]">
                          <button class="not_btn g" @click.prevent="handleAvailableClick(notPossibleData[formatDate(date)]?.[0]?.D配車不可_ID)">配車不可</button>
                        </template>
                        <!-- 配車不可のデータがない場合 -->
                        <template v-else>
                          <div v-if="dispatchDisplay == 0" class="not_btn delete_btn" @click.prevent="handleUnavailableClick(date)">配車不可</div>
                        </template>

                      </template>
                    <template v-else>
                    </template>
                </span>

            </div>
        </div>
    </div>    

    <!-- <Edit ref="editRef"@reLoad="reLoadItems"></Edit> -->
    <Edit ref="editRef" />
    <Add ref="addRef" @reLoad="reLoadItems"></Add>
    <Delete ref="DeleteRef" @reLoad="reLoadItems"/>
  
</section>
</template>

<style>
/* .loading-wrap {
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
} */
@media screen and (max-width: 500px) { /* スマホサイズの条件 */
  .red-line-mobile {
    border-bottom: 3px solid rgb(0, 0, 0) !important;
  }
}

</style>
