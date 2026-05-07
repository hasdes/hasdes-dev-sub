<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useLoading } from 'vue-loading-overlay';

defineProps({
  authItems: Array
})

const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(50);
const loadingActive = ref(false); // ローディング状態を管理する変数
const isDetailsOpen = ref(true);
const currentFilters = ref({});
const stockitems = ref([]);
const stockitemsTotal = ref(0);
const stockpage = ref(1);
const stockpageSize = ref(13);
const currentProductCode = ref(null);

const savedFilters = sessionStorage.getItem('stockFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

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

const sortColumn = ref('商品CD');
const sortOrder = ref('asc');
const stockSortColumn = ref('C商品月間.倉庫部門CD');
const stockSortOrder = ref('asc');

const $loading = useLoading({});

// 商品リストのページ変更時の処理
const setPage = (val) => {
  page.value = val;
  reLoadItems();
};

// 在庫リストのソート切り替え処理
const stockSort = (column) => {
  if (stockSortColumn.value === column) {
    stockSortOrder.value = stockSortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    stockSortColumn.value = column;
    stockSortOrder.value = 'asc';
  }
  // 現在のフィルター情報を保持しつつ、ソート情報を渡す
  fetchStockInfo(currentProductCode.value, selectedItem.value);
};

// 商品リストのソート切り替え処理
const sort = (column) => {
  if (sortColumn.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = column;
    sortOrder.value = 'asc';
  }
  reLoadItems();
};

// 商品リストのデータを再読み込みする関数
const reLoadItems = () => {
  loadingActive.value = true; // ローディング状態を有効に
  // const loader = $loading.show();
  const params = {
    ...currentFilters.value,
    page: page.value,
    pageSize: pageSize.value,
    sortColumn: sortColumn.value,
    sortOrder: sortOrder.value,
  };
  axios
    .get('/api/stock/list', { params })
    .then((res) => {
      items.value = res.data.data;
      itemsTotal.value = res.data.total;

      // デフォルトで1行目を選択
      if (items.value.length > 0) {
        const firstItem = items.value[0];
        selectedItem.value = firstItem;
        fetchStockInfo(firstItem.商品CD, firstItem);
      }
    })
    .catch((error) => {
      console.error('データ取得中にエラーが発生しました:', error);
    })
    .finally(() => {
      loadingActive.value = false; // ローディング状態を解除
      loader.hide();
    });
};

// 在庫照会ページ変更時の処理
const stocksetPage = (val) => {
  stockpage.value = val;
  fetchStockInfo(currentProductCode.value, selectedItem.value);
};

const selectedItem = ref(null);

const fetchStockInfo = (productCode, item) => {
  if (!productCode) {
    console.warn('商品コードが指定されていません。');
    return;
  }
  // ページネーションのリセット
  stockpage.value = 1;
  stockitems.value = [];  // 前回の在庫情報をクリア
  stockitemsTotal.value = 0;  // トータルもリセット

  loadingActive.value = true; // ローディング状態を有効に
  // const loader = $loading.show();
  currentProductCode.value = productCode;
  selectedItem.value = item;

  const params = {
    商品CD: productCode,
    呼び径1: item?.呼び径1,
    呼び径2: item?.呼び径2,
    呼び径3: item?.呼び径3,
    page: stockpage.value,
    pageSize: stockpageSize.value,
    sortColumn: stockSortColumn.value, // ソートカラム
    sortOrder: stockSortOrder.value, // ソート順
  };

  axios.get('/api/stock/stocklist', { params })
    .then((response) => {
      if (response.data && response.data.data && response.data.data.length) {
        stockitems.value = response.data.data;
        stockitemsTotal.value = response.data.total;
      } else {
        stockitems.value = [];
      }
    })
    .catch((error) => {
      console.error('在庫情報の取得中にエラーが発生しました:', error.response || error);
      stockitems.value = [];
    })
    .finally(() => {
      loadingActive.value = false; // ローディング状態を解除
      loader.hide();
    });
};

const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('stockFilters', JSON.stringify(params));
  page.value = 1;
  stockitems.value = []; // 在庫データをリセット
  stockitemsTotal.value = 0; // 在庫件数をリセット
  reLoadItems();
};
const toggleIcon = (event) => {
  isDetailsOpen.value = event.target.open;
};
onMounted(() => {
  const storedData = sessionStorage.getItem('stockFilters');
  if (storedData) {
    reLoadItems();
  }
});
import Filter from '@/Pages/Stock/Filter.vue';
</script>


<template>
<section class="section dashboard">
  <!-- ローディング画面 -->
  <div v-if="loadingActive" class="loading-wrap">
    <span>読み込み中...</span>
  </div>
  <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
          <li>情報表示</li>
          <li>在庫表示</li>
        </ol>

  <!-- 商品リスト -->
  <Filter @search="handleSearch"></Filter>
  <div class="col-lg-12">
    <details class="contents_head" open @toggle="toggleIcon">
      <summary class="send_f">
        <h5 class="card-title">商品一覧</h5>
        <i :class="isDetailsOpen ? 'bi bi-arrow-up' : 'bi bi-arrow-down'"></i>
      </summary>
      <div class="scroll-box s scroll-box_y f">
        <table class="table_w tablesorter back_w" id="table_sort">
          <thead>
            <tr class="head sto">
              <th class="narrow_j" @click="sort('商品種別')">商品種別
                <span v-if="sortColumn === '商品種別' && sortOrder === 'asc'">▲</span>
                <span v-if="sortColumn === '商品種別' && sortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_j" @click="sort('商品CD')">商品CD
                <span v-if="sortColumn === '商品CD' && sortOrder === 'asc'">▲</span>
                <span v-if="sortColumn === '商品CD' && sortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_g" @click="sort('呼び径1')">呼び径
                <span v-if="sortColumn === '呼び径1' && sortOrder === 'asc'">▲</span>
                <span v-if="sortColumn === '呼び径1' && sortOrder === 'desc'">▼</span>
              </th>
              <th @click="sort('商品名_社内用')">商品名
                <span v-if="sortColumn === '商品名_社内用' && sortOrder === 'asc'">▲</span>
                <span v-if="sortColumn === '商品名_社内用' && sortOrder === 'desc'">▼</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="(item, index) in items" 
              :key="index" 
              @click="fetchStockInfo(item.商品CD, item)"
              :class="{ 'selected-item': selectedItem === item }"
            >
              <td data-label="商品種別">{{ getProductTypeName(item.商品種別) }}</td>
              <td data-label="商品CD">{{ item.商品CD }}</td>
              <td data-label="呼び径">
                  <span class="right-align">{{ item.呼び径1 }}</span>
                  <span class="right-align">{{ item.呼び径2 }}</span>
                  <span class="right-align">{{ item.呼び径3 }}</span>
              </td>
              <td data-label="商品名">{{ item.商品名_社内用 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
        <div class="table_fot  pa_top_a" v-if="itemsTotal > 0">
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
    </details>
  </div>

  <!-- 在庫照会リスト -->
  <div class="col-lg-12">
    <div class="card ma_top_a">
      <div class="contents_head">
        <h5 class="card-title">在庫照会</h5>
      </div>
      <div class="scroll-box s scroll-box_y c">
        <table class="table_w tablesorter alter" id="table_sort_a">
          <thead>
            <tr class="head">
              <th class="narrow_k" @click="stockSort('C商品月間.倉庫部門CD')">工場名
                <span v-if="stockSortColumn === 'C商品月間.倉庫部門CD' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === 'C商品月間.倉庫部門CD' && stockSortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_j" @click="stockSort('商品CD')">商品CD
                <span v-if="stockSortColumn === '商品CD' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === '商品CD' && stockSortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_g" @click="stockSort('呼び径1')">呼び径
                <span v-if="stockSortColumn === '呼び径1' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === '呼び径1' && stockSortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_e" @click="stockSort('年号')">年号
                <span v-if="stockSortColumn === '年号' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === '年号' && stockSortOrder === 'desc'">▼</span>
              </th>
              <th class="narrow_j" @click="stockSort('現在庫_出荷予定数')">有効在庫
                <span v-if="stockSortColumn === '現在庫_出荷予定数' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === '現在庫_出荷予定数' && stockSortOrder === 'desc'">▼</span>
              </th>
              <th @click="stockSort('現在庫_完成品数')">完成品
                <span v-if="stockSortColumn === '現在庫_完成品数' && stockSortOrder === 'asc'">▲</span>
                <span v-if="stockSortColumn === '現在庫_完成品数' && stockSortOrder === 'desc'">▼</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="stockitems.length === 0">
              <td colspan="6">データがありません。</td>
            </tr>
            <tr v-for="(stockitem, index) in stockitems" :key="index">
              <td data-label="工場名">{{ stockitem.部門略称名 }}</td>
              <td data-label="商品CD">{{ stockitem.商品CD }}</td>
              <td data-label="呼び径">
                  <span class="right-align">{{ stockitem.呼び径1 }}</span>
                  <span class="right-align">{{ stockitem.呼び径2 }}</span>
                  <span class="right-align">{{ stockitem.呼び径3 }}</span>
              </td>
              <td data-label="年号">{{ stockitem.年号 }}</td>
              <td data-label="有効在庫" style="font-weight: 600;">{{ stockitem.現在庫_完成品数-stockitem.現在庫_出荷予定数 }}</td>
              <td class="red-line-mobile" data-label="完成品">{{ stockitem.現在庫_完成品数 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  
    <div class="table_fot" v-if="stockitemsTotal > 0">
    <span>
    全 {{ stockitemsTotal }} 件中 
    {{ (stockpage - 1) * stockpageSize + 1 }} 件 〜 
    {{ Math.min(stockpage * stockpageSize, stockitemsTotal) }} 件を表示
    </span>
    <el-pagination
      layout="prev, pager, next"
      :total="stockitemsTotal"
      :page-size="stockpageSize"
      :current-page.sync="stockpage"
      @current-change="stocksetPage"
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
