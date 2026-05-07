<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Add from '@/Pages/ConverShipping/Add.vue';//追加
import Update from '@/Pages/ConverShipping/Update.vue';

defineProps({
  authItems: Array
})

const router = useRouter(); // ← ここで router を取得
// const loadingActive = ref(false); // ローディング状態を管理する変数

// const item = ref({ 変換名: '' });
const item = ref(null);   // ★ null の方が正しい
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数
const sortColumn = ref('HD変換出荷先_ID'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順
const updateRef = ref();//削除ポップアップ

// const syozokubumon = ref(null) // 管轄部門・営業所


// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key'); // URLから 'key' パラメータを取得
};

// 出荷先情報の読み込み
const reLoadItem = () => {
  // loadingActive.value = true; // ローディング状態を有効に
  const key = getKeyFromUrl();
  console.log("🔑 URLから取得した key:", key);

  if (!key) {
    console.error('URLにkeyパラメータがありません。');
    // loadingActive.value = false;
    // loader.hide();// ローディングを非表示
    return;
  }

  // ★ここでセットする（item.value が確実に存在するタイミング）
  // syozokubumon.value = item.value?.['管轄部門CD'] ?? null
  // console.log('管轄部門CD確認：', syozokubumon.value)


  // axios.get('/api/shipping/detail', { params: { key, syozokubumon } })
  axios.get('/api/shipping/detail', { params: { key } })
  .then(res => {
    if (!res.data?.data) throw new Error('データが存在しません');
    item.value = res.data.data;
    reLoadItems(); // 得意先が取得できたら変換データ取得
  })
  .catch(error => {
    console.error("shipping/detail ERROR:", error.response?.data ?? error);
  })

  .finally(() => {
    // loadingActive.value = false; // ローディング状態を解除
  });
}

// 変換データの再取得（ページ変更・ソート変更時）
const reLoadItems = async () => {
  // console.log('item:', item.value);
  if (!item.value?.出荷先_エンドユーザーCD) return;
    // loadingActive.value = true;
    const params = {
      // key: getKeyFromUrl(),
      // cd: item.value.出荷先_エンドユーザーCD,
      key: item.value.出荷先_エンドユーザーCD,
      // key2: item.value.管轄部門CD,
      page: page.value,
      pageSize: pageSize.value, // ページサイズをパラメータに追加
      sortColumn: sortColumn.value, // ソート対象カラム
      sortOrder: sortOrder.value, // ソート順
    };
    try {
      const res = await axios.get('/api/convershipping/list', { params });
      items.value = res.data.data || [];
      itemsTotal.value = res.data.total || 0;
    } catch (error) {
      console.error('convershipping/list ERROR:', error.response?.data ?? error)
    }
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

// 編集ページへ遷移する関数
const goToEdit = (mId, dId) => {
  if (!mId || !dId) {
    console.error('必要なIDが存在しません');
    return;
  }
  router.push({
    path: '/convershipping/edit',
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
  <ol class="breadcrumb">
    <!-- <li><router-link to="/home">ホーム</router-link></li> -->
    <li v-if="authItems?.[0]?.ホーム == 0">
      <router-link to="/home">ホーム</router-link>
    </li>
    <li>販売管理</li>
    <li><router-link to="/conver">OCR変換マスタ</router-link></li>
    <li><router-link to="/convershipping/">出荷先編集</router-link></li>
    <li>出荷先変換</li>
  </ol>

  <!-- ローディング画面 -->
  <!-- <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div> -->

  <!-- 変換データ -->
  <div class="col-lg-12">
  <div class="card">
      <div class="contents_head">
        <h5 class="card-title">変換データ</h5>
      </div>
      <div class="row space align-center justify-content-between page c space_d">   
          <div class="col-md-6 head_data">
            <label class="col-form-label">出荷先CD</label>                       
            <span class="white-space">{{ item?.出荷先_エンドユーザーCD !== undefined ? item.出荷先_エンドユーザーCD : '' }}</span>    
          </div> 
          <div class="col-md-6 head_data">
            <label class="col-form-label">出荷先名
            </label>   
            <span class="white-space">{{ item?.略名 !== undefined ? item.略名 : '' }}</span>      
            
          </div>     
                                                                                                                      
        </div>              
    </div>
  </div>  

  <!-- 変換パターン-->
  <Add v-if="item?.出荷先_エンドユーザーCD" :cd="item.出荷先_エンドユーザーCD" @reLoad="reLoadItems" />

  <!-- 変換情報一覧 -->
  <div class="col-lg-12">
    <div class="card">
        <div class="contents_head">
          <h5 class="card-title">出荷先情報</h5>
        </div>
        <!-- <div class="scroll-box s scroll-box_y d" v-show="!loadingActive"> -->
        <div class="scroll-box s d">
          <table class="table_w tablesorter alter" id="table_sort"> 
            <thead>
              <tr class="head">
                <!-- <th class="narrow_d" @click="sort('HD変換出荷先_ID')"> -->
                <th @click="sort('HD変換出荷先_ID')">
                  変換名
                  <span v-if="sortColumn === 'HD変換出荷先_ID' && sortOrder === 'asc'">▲</span>
                  <span v-if="sortColumn === 'HD変換出荷先_ID' && sortOrder === 'desc'">▼</span>
                </th>
                <th class="narrow_e"></th>
                <th class=""></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(i, index) in items" :key="index">
                <td class="convert">{{ i.変換名 }}</td>                 
                <td class="sp_btn">
                  <button @click.prevent="goToEdit(item.M出荷先_ID, i.HD変換出荷先_ID)" type="button" class="bo_btn">変更</button>
                </td>
                <td class="sp_btn">
                  <div class="bo_btn mi delete_btn" @click.prevent="updateRef.open(i.HD変換出荷先_ID)">削除</div>
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
  <Update v-if="item?.出荷先_エンドユーザーCD" :shippingId="getKeyFromUrl()" ref="updateRef" @reLoad="reLoadItems"></Update>
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