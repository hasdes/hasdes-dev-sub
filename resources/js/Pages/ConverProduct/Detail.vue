<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Add from '@/Pages/ConverProduct/Add.vue';//追加
import Update from '@/Pages/ConverProduct/Update.vue';

defineProps({
  authItems: Array
})

const router = useRouter(); // ← ここで router を取得
const item = ref(null);
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数
const sortColumn = ref('HD変換商品_ID'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順
// const loadingActive = ref(false); // ローディング状態を管理する変数
const updateRef = ref();//削除ポップアップ

// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key'); // URLから 'key' パラメータを取得
};

// 商品情報の読み込み
const reLoadItem = () => {
  // loadingActive.value = true; // ローディング状態を有効に
  const key = getKeyFromUrl();
  // console.log('key:', key);

  if (!key) {
    console.error('URLにkeyパラメータがありません。');
      loadingActive.value = false; // ローディング状態を解除
      loader.hide();// ローディングを非表示
    return;
  }

  axios.get('/api/item/detail', { params: { key } })
    .then(res => {
      if (!res.data?.data || res.data.data.length === 0) {
        throw new Error('データが存在しません');
      }
      item.value = res.data.data[0];
      reLoadItems(); // ← ここが実行されるかどうか
    })
    .catch(err => {
      console.error('item/detail API失敗:', err);
    })
    .finally(() => {
      // loadingActive.value = false; // ローディング状態を解除
    });
};

// 変換データの再取得（ページ変更・ソート変更時）
const reLoadItems = async () => {
  if (!item.value?.商品CD) return;
    // loadingActive.value = true;

    const params = {
      // key: getKeyFromUrl(),
      key: item.value.商品CD,
      page: page.value,
      pageSize: pageSize.value, // ページサイズをパラメータに追加
      sortColumn: sortColumn.value, // ソート対象カラム
      sortOrder: sortOrder.value, // ソート順
    };
    try {
      const res = await axios.get('/api/converproduct/list', { params });
      items.value = res.data.data || [];
      itemsTotal.value = res.data.total || 0;
    } catch (error) {
      console.error(error);
    } finally {
      // loadingActive.value = false;
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


// 商品 編集ページへ遷移する関数を追加
const goToEdit = (mId, dId) => {
  if (!mId || !dId) {
    console.error('必要なIDが存在しません');
    return;
  }
  router.push({
    path: '/converproduct/edit',
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
    <li><router-link to="/converproduct/">商品編集</router-link></li>
    <li>商品変換</li>
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
            <label class="col-form-label">商品CD</label>                       
            <span class="white-space">{{ item?.商品CD !== undefined ? item.商品CD : '' }}</span>    
          </div> 
          <div class="col-md-6 head_data">
            <label class="col-form-label">商品名
            </label>   
            <span>{{ item?.商品名_社内用 !== undefined ? item.商品名_社内用 : '' }}</span>      
          </div>     
        </div>              
    </div>
  </div>  

  <!-- 変換パターン-->
  <Add v-if="item?.商品CD" :productCd="item.商品CD" :productId="getKeyFromUrl()" @reLoad="reLoadItems" />

  <!-- 変換情報一覧 -->
  <div class="col-lg-12">
    <div class="card">
        <div class="contents_head">
          <h5 class="card-title">商品情報</h5>
        </div>
        <!-- <div class="scroll-box s scroll-box_y d" v-show="!loadingActive"> -->
        <div class="scroll-box s d">
          <table class="table_w tablesorter alter" id="table_sort"> 
            <thead>
              <tr class="head">
                <!-- <th class="narrow_d" @click="sort('HD変換商品_ID')"> -->
                <th @click="sort('HD変換商品_ID')">
                  変換名
                  <span v-if="sortColumn === 'HD変換商品_ID' && sortOrder === 'asc'">▲</span>
                  <span v-if="sortColumn === 'HD変換商品_ID' && sortOrder === 'desc'">▼</span>
                </th>
                <th class="narrow_e"></th>
                <th class=""></th>
              </tr>
            </thead>
            <tbody>

              <tr v-for="(i, index) in items" :key="index">
                <td class="convert">{{ i.変換名 }}</td>                 
                <td class="sp_btn">
                  <button @click.prevent="goToEdit(item.M商品_ID, i.HD変換商品_ID)" type="button" class="bo_btn">変更</button>
                </td>
                <td class="sp_btn">
                  <div class="bo_btn mi delete_btn" @click.prevent="updateRef.open(i.HD変換商品_ID)">削除</div>
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
  <Update v-if="item?.商品CD" :productId="getKeyFromUrl()" ref="updateRef" @reLoad="reLoadItems"></Update>

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