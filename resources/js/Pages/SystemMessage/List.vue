<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';
import { useRouter } from 'vue-router'; // Vue Routerのインポート

defineProps({
  authItems: Array
})

const router = useRouter(); // ルーターインスタンスの取得

const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(16); 
const loading = ref(false);

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('systemmessageFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

const sortColumn = ref('送信日時'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('desc'); // 初期表示時のデフォルトソート順

// ページ変更時の処理
const setPage = (val) => {
  page.value = val;
  reLoadItems();
};

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
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/systemmessage/list', { params })
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
  reLoadItems();
});

// 子コンポーネントから検索条件を受け取る関数
const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('systemmessageFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

// 詳細ページへ遷移する関数を追加
const goToDetail = (messageCode) => {
  router.push({ path: '/systemmessage/detail', query: { key: messageCode } });
};

const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD');
};

import Filter from '@/Pages/SystemMessage/Filter.vue';
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
          <li>メッセージ管理</li>
        </ol> 
        <Filter @search="handleSearch"></Filter>
        <div class="col-lg-12">          
          <div class="card">
              <div class="contents_head">
                <h5 class="card-title">メッセージ一覧</h5>
              </div>                     
              <div class="scroll-box s scroll-box_y c">
                <table class="table_w tablesorter none sp_w" id="table_sort_b"> 
                    <thead>
                      <tr class="head">
                        <th class="narrow_b"@click="sort('送信日時')">日付
                          <span v-if="sortColumn === '送信日時' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '送信日時' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_b"@click="sort('送信者CD')">担当者CD
                          <span v-if="sortColumn === '送信者CD' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '送信者CD' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_b"@click="sort('送信者名')">担当者名
                          <span v-if="sortColumn === '送信者名' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '送信者名' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_b"@click="sort('送信者所属名_社内用')">所属
                          <span v-if="sortColumn === '送信者所属名_社内用' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '送信者所属名_社内用' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_b"@click="sort('受信者名')">相手名
                          <span v-if="sortColumn === '受信者名' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '受信者名' && sortOrder === 'desc'">▼</span>
                        </th>             
                        <th class="narrow_b">所属部門</th>  
                        <th class="narrow_b">所属</th>  
                        <th class="sm"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, index) in items" :key="index">
                        <th class="item_f" data-label="日付">{{ formatDate(item.送信日時) }}</th>
                        <td data-label="担当者CD">{{ item.送信者CD }}</td>
                        <td data-label="担当者名">{{ item.送信者名 }}</td>
                        <td data-label="所属">{{ item.送信者所属名_社内用 }}</td>
                        <td data-label="相手名">{{ item.受信者名 }}</td>
                        <td data-label="所属部門">{{ item.受信者部門略称名 }}</td>
                        <td data-label="所属">{{ item.受信者所属名_社内用_受信者 }}</td>
                        <td class="sp_btn">
                          <button @click.prevent="goToDetail(item.メッセージCD)" type="button" class="button_r none search">詳細</button>
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
      </div>
    </section>
</template>
