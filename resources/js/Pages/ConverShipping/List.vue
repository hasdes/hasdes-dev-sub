<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router'; // Vue Routerのインポート
// import { useLoading } from 'vue-loading-overlay';//読み込み中表示
import Filter from '@/Pages/ConverShipping/Filter.vue';//検索

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得
// const $loading = useLoading({});// ローダーインスタンスを作る
// const loadingActive = ref(false); // ローディング状態を管理する変数

const items = ref([]);
const itemsTotal = ref(0);

//ページング
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数

// ソートに関する状態
const sortColumn = ref('出荷先_エンドユーザーCD'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('asc'); // 初期表示時のデフォルトソート順

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('shippingFilters');

if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

//親コンポーネントから受け取る
// const props = defineProps({ items: { type: Array, required: true } });

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

// データを再読み込みする関数
const reLoadItems = () => {
  // loadingActive.value = true; // ローディング状態を有効に
  // const loader = $loading.show();// ローディングを表示


  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    page: page.value,
    pageSize: pageSize.value, // ページサイズをパラメータに追加
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
    // syozokubumon: syozokubumon.value, // 所属部門（管轄部門CD）
  };
  axios
    .get('/api/shipping/list', { params })
    .then((res) => {
      items.value = res.data.data;
      itemsTotal.value = res.data.total;
    })
    .catch((error) => {
      console.error('データ取得中にエラーが発生しました:', error);
    })
    // .finally(() => {
      // loadingActive.value = false; // ローディング状態を解除
      // loader.hide();// ローディングを非表示
    // });
};

// 子コンポーネントから検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('shippingFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

// 詳細ページへ遷移する関数を追加
const goToDetail = (ShippingCode) => {
  router.push({ path: '/convershipping/detail', query: { key: ShippingCode } });
};

// ページ変更時の処理
const setPage = (val) => {
  page.value = val;
  reLoadItems();
};

// 初回読み込み
onMounted(() => {
  reLoadItems(); // 初期表示時にデフォルトソートでデータを読み込み
});

// const syozokubumon = ref(null)

// // props.items を見て所属部門を更新
// watch(() => props.items, (newItems) => {
//   if (newItems?.length > 0) {
//     syozokubumon.value = newItems[0]['所属部門CD'] ?? null
//     console.log('★所属部門CDがセットされた:', syozokubumon.value)
//     reLoadItems()
//   }
// }, { immediate: true })
</script>



<template>
<section class="section dashboard">
  <ol class="breadcrumb">
    <!-- <li><router-link to="/home">ホーム</router-link></li> -->
    <li v-if="authItems?.[0]?.ホーム == 0">
      <router-link to="/home">ホーム</router-link>
    </li>
    <li>販売管理</li>
    <li><router-link to="/conver">OCR変換マスタ</router-link></li>
    <li>出荷先編集</li>
  </ol>

  <!-- ローディング画面 -->
  <!-- <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div> -->

  <!-- 検索条件 -->
  <Filter @search="handleSearch"></Filter>

  <!-- 出荷先一覧 -->
    <div class="col-lg-12">
      <div class="card">
          <div class="contents_head">
            <h5 class="card-title">出荷先一覧</h5>
          </div>
          <div class="scroll-box s scroll-box_y d">
            <table class="table_w tablesorter alter" id="table_sort"> 
              <thead>
                <tr class="head">
                  <th class="narrow_a" @click="sort('出荷先_エンドユーザーCD')">
                    出荷先CD
                    <span v-if="sortColumn === '出荷先_エンドユーザーCD' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '出荷先_エンドユーザーCD' && sortOrder === 'desc'">▼</span>
                  </th>
                  <th class="narrow_d" @click="sort('得意先略名')">
                    出荷先名（略）
                    <span v-if="sortColumn === '略名' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '略名' && sortOrder === 'desc'">▼</span>
                  </th>
                  <th class="sm"></th>
                </tr>

              </thead>
              <!-- <tbody v-show="!loadingActive"> -->
              <tbody>
                <tr v-for="(item, index) in items" :key="index">
                  <th class="item_f white-space">{{ item.出荷先_エンドユーザーCD }}</th>
                  <td class="white-space">{{ item.略名 }}</td>
                  <td class="sp_btn">
                    <button @click.prevent="goToDetail(item.M出荷先_ID)" type="button" class="bo_btn">編集</button>
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
  background-color: rgba(255, 255, 255, 0.5);
  z-index: 2;
  font-size: 1.5em;
  transform: translate(-50%, -50%);
}
@media screen and (max-width: 500px) { /* スマホサイズの条件 */
  .red-line-mobile {
    border-bottom: 3px solid rgb(0, 0, 0) !important;
  }
}
</style>
