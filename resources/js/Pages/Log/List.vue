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
const pageSize = ref(100); // 1ページあたりの表示件数
const loading = ref(false);

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('logFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

// ソートに関する状態
const sortColumn = ref('登録日時'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順

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
    .get('/api/HDLog/list', { params })
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



// 子コンポーネントから検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('logFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

onMounted(() => {
  reLoadItems(); // 初期表示時にデフォルトソートでデータを読み込み
});
const JOB_TYPES = {
  0: '認証ログ',
  1: 'イベントログ',
  2: '操作ログ',
  3: '変更・更新ログ',
  4: '新規追加',
};
const getJobTypeName = (JobType) => {
  return JOB_TYPES[JobType] || '不明';
};

const SQL_TYPES = {
  0: 'なし',
  1: '有り',
};
const getSqlTypeName = (SqlType) => {
  return SQL_TYPES[SqlType] || '不明';
};

const Erorr_TYPES = {
  0: 'なし',
  1: 'あり',
};
const getErorrTypeName = (ErorrType) => {
  return Erorr_TYPES[ErorrType] || '不明';
};



// 詳細ページへ遷移する関数を追加
const goToDetail = (customerCode) => {
  router.push({ path: '/Log/detail', query: { key: customerCode } });
};



const exportCsv = () => {
  const params = {
    ...currentFilters.value,
    sortColumn: sortColumn.value,
    sortOrder: sortOrder.value,
  };
  axios
    .get('/api/HDLog/exportCsv', {
      params,
      responseType: 'blob', // CSVファイルをバイナリデータで受け取る
    })
    .then((response) => {
      const blob = new Blob([response.data], { type: 'text/csv' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'HDLog.csv';
      link.click();
    })
    .catch((error) => {
      console.error('CSVエクスポート中にエラーが発生しました:', error);
    });
};

import Filter from '@/Pages/Log/Filter.vue';
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
          <li>ログ管理</li>
        </ol> 
        <Filter @search="handleSearch"></Filter>
        <div class="col-lg-12">
          <div class="card">
              <div class="contents_head">
                <h5 class="card-title">ログ一覧</h5>
              </div>          
              <div class="scroll-box s scroll-box_y e">
                  <table class="table_w tablesorter alter none" id="table_sort"> 
                  <thead>
                    <tr class="head">
                      <th class="narrow_d"@click="sort('登録日時')">日付
                        <span v-if="sortColumn === '登録日時' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '登録日時' && sortOrder === 'desc'">▼</span></th>
                      <th class="narrow_a"@click="sort('担当者CD')">担当者CD
                        <span v-if="sortColumn === '担当者CD' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '担当者CD' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_a"@click="sort('担当者名')">担当者名
                        <span v-if="sortColumn === '担当者名' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '担当者名' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_a"@click="sort('ログ種別')">ログ種別
                        <span v-if="sortColumn === 'ログ種別' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === 'ログ種別' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_h"@click="sort('実行内容')">実行内容
                        <span v-if="sortColumn === '実行内容' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '実行内容' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_a"@click="sort('SQL種別')">SQL発行
                        <span v-if="sortColumn === 'SQL種別' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === 'SQL種別' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_a"@click="sort('エラー	')">Error
                        <span v-if="sortColumn === 'エラー	' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === 'エラー	' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_e"></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in items" :key="index">
                    <th class="item_f">{{ item.登録日時 }}</th>
                    <td data-label="ID">{{ item.担当者CD }}</td>
                    <td data-label="担当者名">{{ item.担当者名 }}</td>
                    <td data-label="動作種別">{{ getJobTypeName(item.ログ種別) }}</td>
                    <td data-label="実行内容">{{ item.実行内容 }}</td>
                    <td data-label="SQL種別">{{ getSqlTypeName(item.SQL種別) }}</td>
                    <td data-label="エラー">{{ getErorrTypeName(item.エラー) }}</td>
                    <td class="sp_btn">
                      <button @click.prevent="goToDetail(item.HDログID)" type="button" class="button_r none search">詳細</button>
                    </td>
                  </tr>
                
                </tbody>                           
                </table>
              </div>
          </div>
        </div>

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
      
      <button  class="button_r none search" @click="exportCsv">CSV出力</button>   
      </div>
    </section>
</template>
<style scoped>
/* セルのパディングを0に設定 */
.el-table .el-table__cell {
  padding: 0px !important;
}
</style>