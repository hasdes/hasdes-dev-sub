<script setup>
import { onMounted, ref } from 'vue';
import { useLoading } from 'vue-loading-overlay';
import axios from 'axios';
import { useRouter } from 'vue-router';

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得
const $loading = useLoading({});

// データを管理するリアクティブ変数
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(16); // 1ページあたりの表示件数
const loadingActive = ref(false); // ローディング状態を管理する変数
const currentFilters = ref({}); // 現在の検索条件

const savedFilters = sessionStorage.getItem('itemFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

// ソートに関する状態
const sortColumn = ref('商品種別'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('asc'); // 初期表示時のデフォルトソート順

// 商品種別の数値を対応する文字列に変換する関数
const PRODUCT_TYPES = {
  1: '1:異形管',
  2: '2:バルブ',
  3: '3:筺類',
  4: '4:接合部分',
  5: '5:その他',
};
const getProductTypeName = (productType) => {
  return PRODUCT_TYPES[productType] || '不明';
};

// ページを変更する関数
const setPage = (val) => {
  page.value = val;
  reLoadItems();
};

const sort = (column) => {
  if (sortColumn.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }
  reLoadItems();
};

// データを再読み込みする関数
const reLoadItems = () => {
  loadingActive.value = false; // ローディング状態を有効に
  const loader = $loading.show();
  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    page: page.value,
    pageSize: pageSize.value,
    sortColumn: sortColumn.value,
    sortOrder: sortOrder.value,
  };
  axios
    .get('/api/item/list', { params })
    .then((res) => {
      items.value = res.data.data;
      itemsTotal.value = res.data.total;
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
  sessionStorage.setItem('itemFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

const goToDetail = (messageCode) => {
  router.push({ path: '/item/detail', query: { key: messageCode } });
};

onMounted(() => {
  const storedData = sessionStorage.getItem('itemFilters');
  if (storedData) {
    reLoadItems();
  }
});


// Filterコンポーネントのインポート
import Filter from '@/Pages/Item/Filter.vue';
</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>情報表示</li>
        <li>商品表示</li>
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
            <h5 class="card-title">商品一覧</h5>
          </div>
          <div class="scroll-box s scroll-box_y d">
            <!-- データテーブル -->
            <table class="table_w tablesorter alter" id="table_sort">   
                <thead>
                  <tr class="head">
                    <th class="narrow_a" @click="sort('商品種別')">商品種別
                      <span v-if="sortColumn === '商品種別' && sortOrder === 'asc'">▲</span>
                      <span v-if="sortColumn === '商品種別' && sortOrder === 'desc'">▼</span>
                    </th>
                    <th class="narrow_a" @click="sort('商品CD')">商品CD
                      <span v-if="sortColumn === '商品CD' && sortOrder === 'asc'">▲</span>
                      <span v-if="sortColumn === '商品CD' && sortOrder === 'desc'">▼</span>
                    </th>
                    <th class="narrow_d" @click="sort('呼び径1')">呼び径
                      <span v-if="sortColumn === '呼び径1' && sortOrder === 'asc'">▲</span>
                      <span v-if="sortColumn === '呼び径1' && sortOrder === 'desc'">▼</span>
                    </th>
                    <th class="narrow_f" @click="sort('商品名_社内用')">商品名
                      <span v-if="sortColumn === '商品名_社内用' && sortOrder === 'asc'">▲</span>
                      <span v-if="sortColumn === '商品名_社内用' && sortOrder === 'desc'">▼</span>
                    </th>
                    <th class="sm"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in items" :key="index">
                    <th class="item_f">{{ getProductTypeName(item.商品種別) }}</th>
                    <td data-label="商品CD">{{ item.商品CD }}</td>
                    <td data-label="呼び径">
                        <span class="right-align">{{ item.呼び径1 }}</span>
                        <span class="right-align">{{ item.呼び径2 }}</span>
                        <span class="right-align">{{ item.呼び径3 }}</span>
                    </td>
                    <td data-label="商品名">{{ item.商品名_社内用 }}</td>      
                    <td class="sp_btn">
                      <button @click.prevent="goToDetail(item.M商品_ID)" type="button" class="button_r none search">詳細</button>
                    </td>
                  </tr> 
                </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ページネーション -->
      <div class="table_fot" v-show="!loadingActive" v-if="itemsTotal > 0">
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
    </div>
  </section>
</template>

<style>
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
</style>
