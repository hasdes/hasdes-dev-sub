<script setup>
import { onMounted, ref } from 'vue';
import { useLoading } from 'vue-loading-overlay';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Filter from '@/Pages/Pickup/Filter.vue';// Filterコンポーネントのインポート

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得
const $loading = useLoading({});

// データを管理するリアクティブ変数
const items = ref([]);
const loadingActive = ref(false); // ローディング状態を管理する変数

//検索セッション
const currentFilters = ref({}); // 現在の検索条件
const savedFilters = sessionStorage.getItem('pickupFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

//--------------------------------
//【1】ソート状態を配列で持つ
const sortConditions = ref([
  { column: '作業状態', order: 'desc' },
  { column: '運送会社CD', order: 'desc' },
  { column: '出荷形態', order: 'desc' }
]);

//【2】共通ソート関数を作る
const changeSort = (column) => {
  const index = sortConditions.value.findIndex(s => s.column === column);

  let order = 'desc';

  if (index > -1) {
    order = sortConditions.value[index].order === 'desc' ? 'asc' : 'desc';
    sortConditions.value.splice(index, 1);
  }

  sortConditions.value.unshift({ column, order });

  // console.log('現在のsortConditions:', sortConditions.value);
  reLoadItems();
};

const getSort = (column) => {
  return sortConditions.value.find(s => s.column === column);
};
//--------------------------------


// 作業状態の数値を対応する文字列に変換する関数
const Status_TYPES = {
  0: '未集荷',
  1: '中断',
  2: '済',//処理済み
  3: 'キャンセル',//処理済み
  4: 'キャンセル',//未集荷
  5: 'キャンセル',//処理済みデータがキャンセル未処理
  6: '未集荷',//再集荷
  7: '中断'//再集荷+中断
};
const getStatusTypeName = (statusType) => {
  const type = Number(statusType);
  return Status_TYPES[type] ?? '';
};

// 出荷形態の数値を対応する文字列に変換する関数
const Shipping_TYPES = {
  0: '引取',
  1: '小口',
  2: '直送',
  3: 'コンテナ'
};
const getShippingTypeName = (shippingType) => {
  const type = Number(shippingType);
  return Shipping_TYPES[type] ?? '不明';
};

//出荷指示書遷移ボタン名前
const btnName = (作業状態) => {
  switch (作業状態) {
    case 5:
      return '修正'
    case 2:
    case 3:
    case 4:
      return '確認'
    default:
      return '集荷開始'
  }
}


// データを再読み込みする関数
const reLoadItems = (isSearch = false) => {
  loadingActive.value = false; // ローディング状態を有効に
  const loader = $loading.show();
  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    sorts: sortConditions.value, //ソート追加
  };

  //検索フラグ
  if (isSearch) {
    params.search = true;
  }

  axios
  .get('/api/pickup/list', { params })
  .then((res) => {
    items.value = res.data.data;
    // console.log("API成功:", res);
  })
  .catch((error) => {
    console.error('データ取得中にエラーが発生しました:', error);
  })
  .finally(() => {
    loadingActive.value = false; // ローディング状態を解除
    loader.hide();
  });
};


// 子コンポーネントから検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('pickupFilters', JSON.stringify(params));
  reLoadItems();
};

//出荷指示書(key:出荷指示NO,key2:集荷済出荷指示NO)へ遷移
const goToDetail = (messageCode, 元出荷指示NO = null) => {
  router.push({ path: '/pickup/edit', query: { key: messageCode, key2: 元出荷指示NO } });
};

// 再集荷ボタン押下 --------------------------------------
const showModal = ref(false);
const result = ref([]);
const key = ref(null);
const pickup = ref(null);

const search = (出荷指示NO, 受注_移動NO) => {
  axios.get('/api/pickup/search', {
    params: { 出荷指示NO, 受注_移動NO }
  })
  .then(res => {
    pickup.value = res.data.pickup;
    result.value = res.data.result;
    key.value = 出荷指示NO;// 出荷指示NO

    // console.log('key.value',key.value)

    //HD出荷指示伝票ログに出荷指示NOがあれば（中断、処理済の場合）
    if (pickup.value) {
      router.push({path: '/pickup/edit', query: {key: 出荷指示NO,key2: pickup.value.元出荷指示NO ?? null }})
    } else {
      showModal.value = true //未集荷
    }
  })
  .catch(err => console.error(err));
};
// ---------------------------------------------------


//初期
onMounted(() => {
  reLoadItems();
});

</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>集荷処理</li>
        <li>集荷一覧</li>
      </ol>
      
      <!-- ローディング画面 -->
      <div v-if="loadingActive" class="loading-wrap">
        <span>読み込み中...</span>
      </div>

      <!-- 検索フィルタのコンポーネント -->
      <Filter @search="handleSearch"></Filter>
      
      <div class="col-lg-12" v-show="!loadingActive">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">集荷情報</h5>
          </div>
          <div class="scroll-box s scroll-box_y d c table-container">
            <!-- データテーブル -->
            <table class="table_w tablesorter alter none btn_w sp_w table_co" id="table_sort">   
                <thead>
                  <tr class="head">
                    <th class="narrow_c" @click="changeSort('作業状態')">
                      状態
                      <span v-if="getSort('作業状態')?.order === 'asc'">▲</span>
                      <span v-if="getSort('作業状態')?.order === 'desc'">▼</span>
                    </th>               
                    <th class="narrow_c">出荷日</th>     
                    <th class="narrow_n">受注No</th>
                    <th class="narrow_m">納入先</th>     
                    <th class="narrow_f">納入先住所</th>
                    <th class="narrow_d" @click="changeSort('運送会社CD')">
                      運送会社
                      <span v-if="getSort('運送会社CD')?.order === 'asc'">▲</span>
                      <span v-if="getSort('運送会社CD')?.order === 'desc'">▼</span>
                    </th>                    
                    <th class="narrow_d" @click="changeSort('出荷形態')">
                      出荷形態
                      <span v-if="getSort('出荷形態')?.order === 'asc'">▲</span>
                      <span v-if="getSort('出荷形態')?.order === 'desc'">▼</span>
                    </th>                    
                    <th class="narrow_c">合計数量</th>
                    <th class="narrow_c">合計重量</th>
                    <th></th>
                    <th></th>
                  </tr>
                </thead>
                    <tbody>
                      <tr v-for="(item, index) in items" :key="index"
                      :class="{
                                'error': item.作業状態 == 5,
                                'grayout': [2, 3, 4].includes(item.作業状態)
                              }"
                      >
                        <td data-label="状態">{{ getStatusTypeName(item.作業状態) }}</td>
                        <td data-label="出荷日">{{ item.出荷日 }}</td>  
                        <td data-label="受注No">{{ item.受注_移動NO }}</td>  
                        <td data-label="納入先">{{ item.略名 }}</td>
                        <td data-label="納入先住所">{{ item.住所1 }}{{ item.住所2 }}</td>                                             
                        <td data-label="運送会社">{{ item.運送会社名 }}</td>
                        <td data-label="出荷形態">{{ getShippingTypeName(item.出荷形態) }}</td>
                        <td data-label="合計数量">{{ item.出荷指示数量合計 }}</td>
                        <td data-label="重量合計">{{ item.重量合計 }}</td>
                        <td class="sp_btn"><button @click.prevent="goToDetail(item.出荷指示NO)" class="bo_btn se e">{{ btnName(item.作業状態) }}</button></td>
                        <td class="sp_btn"><button @click.prevent="search(item.出荷指示NO,item.受注_移動NO)" class="bo_btn e" v-if="[6,7].includes(item.作業状態)">再集荷</button></td>
                      </tr>
                    </tbody>               
            </table>
          </div>
        </div>
      </div>

    <!-- ======= 商品名検索ポップアップ ======= -->
    <div class="modal_wrap" v-if="showModal">
      <div class="modal_inner s">
          <div class="signup_form" style="margin-bottom:20px;">
            <h5>再集荷する出荷指示Noを選択してください。</h5>
          </div>
          <form action="">
              <div class="scroll-box_y f" v-if="result">
                  <table class="table_w tablesorter alter pop_table">   
                    <tr v-for="item in result" class="clickable" @click="goToDetail(key, item.出荷指示NO)">
                      <td>No.{{ item.出荷指示NO }}</td>
                    </tr>
                  </table>
              </div>
          </form>       
         <div class="btn_space_modal">
           <div class="submit_btn_no" @click="showModal = false">キャンセル</div>
         </div>                   
      </div>
    </div> 

    </div>
  </section>
</template>

<style scoped>
.loading-wrap {
  position: fixed;
  width: 100vw;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(255, 255, 255, 0.8);
  z-index: 2;
  font-size: 1.5em;
}
.table_w thead th {
  position: sticky;
  top: 0;
  background: #fff; /* 背景必須（下の行が透けるため） */
  z-index: 10;
}
.table-container {
  container-type: inline-size;
}
@container (max-width: 1366px) {
  button.bo_btn.e {
    padding: 12px 0px;
    margin: 10px 2px;
  }
}
@container (max-width: 1200px) {
  .table_w th {
    padding: 12px 5px;
  }
  .table_w td  {
    padding: 0px 5px;
  }
  button.bo_btn.e {
    width: 60px;
    padding: 10px 1px;
  }
}
@media screen and (min-width: 591px) and (max-width: 1000px) {
    .sp_w {
        min-width: 1100px;
    }
}
</style>
