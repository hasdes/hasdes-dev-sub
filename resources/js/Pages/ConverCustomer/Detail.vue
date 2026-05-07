<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Add from '@/Pages/ConverCustomer/Add.vue';//追加
import Update from '@/Pages/ConverCustomer/Update.vue';

defineProps({
  authItems: Array
})

const router = useRouter(); // ← ここで router を取得
const item = ref(null);
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数
const sortColumn = ref('HD変換得意先_ID'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順
const loadingActive = ref(false); // ローディング状態を管理する変数
const updateRef = ref();//削除ポップアップ

// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key'); // URLから 'key' パラメータを取得
};

// 得意先情報の読み込み
const reLoadItem = () => {
  // loadingActive.value = true;
  const key = getKeyFromUrl();

  if (!key) {
    console.error('URLにkeyパラメータがありません。');
    loadingActive.value = false;
    return;
  }

  axios.get('/api/customer/detail', { params: { key } })
  .then(res => {
    if (!res.data?.data) throw new Error('データが存在しません');
    item.value = res.data.data;
    reLoadItems(); // 得意先が取得できたら変換データ取得
  })
  .catch(console.error)
  .finally(() => {
    // loadingActive.value = false; // ローディング状態を解除
  });
};

// 変換データの再取得（ページ変更・ソート変更時）
const reLoadItems = () => {
  console.log('item:', item.value);
  if (!item.value?.得意先CD) return;
  // loadingActive.value = true;
  const params = {
    key: item.value.得意先CD,
    page: page.value,
    pageSize: pageSize.value, // ページサイズをパラメータに追加
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/convercustomer/list', { params })
    .then(res => {
      console.log('APIレスポンス:', res.data);
      const responseData = res.data;
      items.value = responseData.data || [];
      itemsTotal.value = responseData.total || 0;   
     })
    .catch((error) => { console.error('データ取得中にエラーが発生しました:', error); })
    .finally(() => {
      // loadingActive.value = false; // ローディング状態を解除
    });
};

// ソート切り替え関数
const sort = (column) => {
  if (sortColumn.value === column) {
    // 同じカラムが再度クリックされたらソート順を切り替え
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    // 新しいカラムがクリックされたらそのカラムで昇順ソート
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }
  reLoadItems();
};

// 得意先 編集ページへ遷移する関数を追加
const goToEdit = (mId, dId) => {
  if (!mId || !dId) {
    console.log('mId:',mId);
    console.log('dId',dId);
    console.error('必要なIDが存在しません');
    return;
  }
  router.push({
    path: '/convercustomer/edit',
    query: { key: mId, d_key: dId }  
  });
};

// ページ変更時の処理
const setPage = (val) => {
  page.value = val;
  reLoadItems();
};

// 初回読み込み
onMounted(() => {
  reLoadItem();
});
</script>


<template>
<section class="section dashboard" v-if="item">
  <!-- ローディング画面 -->
  <!-- <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div> -->
  <ol class="breadcrumb">
    <!-- <li><router-link to="/home">ホーム</router-link></li> -->
    <li v-if="authItems?.[0]?.ホーム == 0">
      <router-link to="/home">ホーム</router-link>
    </li>
    <li>販売管理</li>
    <li><router-link to="/conver">OCR変換マスタ</router-link></li>
    <li><router-link to="/convercustomer/">得意先編集</router-link></li>
    <li>得意先変換</li>
  </ol>

  <!-- 変換データ -->
  <div class="col-lg-12">
  <div class="card">
      <div class="contents_head">
        <h5 class="card-title">変換データ</h5>
      </div>
      <div class="row space align-center justify-content-between page c space_d">   
          <div class="col-md-6 head_data">
            <label class="col-form-label">得意先CD</label>                       
            <span class="white-space">{{ item?.得意先CD !== undefined ? item.得意先CD : '' }}</span>    
          </div> 
          <div class="col-md-6 head_data">
            <label class="col-form-label">得意先名
            </label>   
            <span class="white-space">{{ item?.得意先略名 !== undefined ? item.得意先略名 : '' }}</span>      
            
          </div>     
                                                                                                                      
        </div>              
    </div>
  </div>  

  <!-- 変換パターン-->
  <Add v-if="item?.得意先CD" :customerCd="item.得意先CD" :customerId="getKeyFromUrl()" @reLoad="reLoadItems" />

  <!-- 変換情報一覧 -->
  <div class="col-lg-12">
    <div class="card">
        <div class="contents_head">
          <h5 class="card-title">顧客企業情報</h5>
        </div>
        <!-- <div class="scroll-box s scroll-box_y d"> -->
        <div class="scroll-box s d">
          <table class="table_w tablesorter alter" id="table_sort"> 
            <thead>
              <tr class="head">
                <!-- <th class="narrow_d" @click="sort('HD変換得意先_ID')"> -->
                <th @click="sort('HD変換得意先_ID')">
                  変換名
                  <span v-if="sortColumn === 'HD変換得意先_ID' && sortOrder === 'asc'">▲</span>
                  <span v-if="sortColumn === 'HD変換得意先_ID' && sortOrder === 'desc'">▼</span>
                  <!-- <i class="fa-solid fa-sort"></i>                    -->
                </th>
                <th class="narrow_e"></th>
                <th class=""></th>
              </tr>
            </thead>
            <tbody>

              <tr v-for="(i, index) in items" :key="index">
                <!-- <td data-label="変換名">{{ i.変換名 }}</td>                  -->
                <td class="convert">{{ i.変換名 }}</td>                 
                <td class="sp_btn">
                  <button @click.prevent="goToEdit(item.M得意先_ID, i.HD変換得意先_ID)" type="button" class="bo_btn">変更</button>
                </td>
                <td class="sp_btn">
                  <div class="bo_btn mi delete_btn" @click.prevent="updateRef.open(i.HD変換得意先_ID)">削除</div>
                </td>
              </tr> 

          </tbody>
          </table>
        </div>
    </div>
  </div>
  <div class="table_fot">
    <span v-if="itemsTotal === 0">
      全 0 件
    </span>
    <span v-else>
      全 {{ itemsTotal }} 件中
      {{ (page - 1) * pageSize + 1 }} 件 〜
      {{ Math.min(page * pageSize, itemsTotal) }} 件を表示
    </span>

    <el-pagination
      layout="prev, pager, next"
      :total="itemsTotal"
      :page-size="pageSize"
      :current-page.sync="page"
      @current-change="setPage"
    ></el-pagination>
  </div>

  <!-- 削除ポップアップ -->
  <Update v-if="item?.得意先CD" :customerId="getKeyFromUrl()" ref="updateRef" @reLoad="reLoadItems"></Update>
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

@media (max-width: 767px) {
  table.alter tr td:nth-child(odd) {
    min-width: 100%;
  }
}
</style>