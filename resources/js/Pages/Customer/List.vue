<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router'; // Vue Routerのインポート

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数
const loading = ref(false);

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('customerFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

// ソートに関する状態
const sortColumn = ref('得意先CD'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('asc'); // 初期表示時のデフォルトソート順

// ページ変更時の処理
const setPage = (val) => {
  page.value = val;
  reLoadItems();
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

// データを再読み込みする関数
const reLoadItems = () => {
  loading.value = true;
  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    page: page.value,
    pageSize: pageSize.value, // ページサイズをパラメータに追加
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/customer/list', { params })
    .then((res) => {
      items.value = res.data.data;
      itemsTotal.value = res.data.total;
    })
    .catch((error) => {
      console.error('データ取得中にエラーが発生しました:', error);
    })
    .finally(() => {
      loading.value = false;
    });
};

// 初回読み込み
onMounted(() => {
  reLoadItems(); // 初期表示時にデフォルトソートでデータを読み込み
});

// 子コンポーネントから検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('customerFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

// 詳細ページへ遷移する関数を追加
const goToDetail = (customerCode) => {
  router.push({ path: '/customer/detail', query: { key: customerCode } });
};

import Filter from '@/Pages/Customer/Filter.vue';
</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>システム管理</li>
        <li>顧客企業マスタメンテ</li>
      </ol>
      <Filter @search="handleSearch"></Filter>

      <div class="col-lg-12">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">顧客企業情報</h5>
          </div>
          <div class="scroll-box s scroll-box_y d">
            <table class="table_w tablesorter alter" id="table_sort"> 
              <thead>
                <tr class="head">
                  <th class="narrow_a" @click="sort('得意先CD')">
                    得意先CD
                    <span v-if="sortColumn === '得意先CD' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '得意先CD' && sortOrder === 'desc'">▼</span>
                  </th>
                  <th class="narrow_d" @click="sort('得意先略名')">
                    得意先名（略）
                    <span v-if="sortColumn === '得意先略名' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '得意先略名' && sortOrder === 'desc'">▼</span>
                  </th>
                  <th class="sm"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in items" :key="index">
                  <th class="item_f" data-label="得意先CD">{{ item.得意先CD }}</th>
                  <td data-label="得意先略名">{{ item.得意先略名 }}</td>
                  <td class="sp_btn">
                    <button @click.prevent="goToDetail(item.M得意先_ID)" type="button" class="button_r none search">詳細</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="table_fot">
        <span>
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

