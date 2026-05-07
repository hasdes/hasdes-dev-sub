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
const pageSize = ref(17); 
const loading = ref(false);

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('contentsFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

const sortColumn = ref('更新日時'); // 初期表示時のデフォルトソートカラム
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
    pageSize: pageSize.value, 
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/contents/list', { params })
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
  sessionStorage.setItem('contentsFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD');
};

const goToDetail = (customerCode) => {
  router.push({ path: '/contents/detail', query: { key: customerCode } });
};

const goToEdit = (customerCode) => {
  router.push({ path: '/contents/edit', query: { key: customerCode } });
};

import Update from '@/Pages/Contents/Update.vue'
const updateRef = ref();
import Filter from '@/Pages/Contents/Filter.vue';
</script>

<template>
<section class="section dashboard">
      <div class="row">
        <ol class="breadcrumb">
          <!-- <li><router-link to="/home">ホーム</router-link></li> -->
          <li v-if="authItems?.[0]?.ホーム == 0">
            <router-link to="/home">ホーム</router-link>
          </li>
          <li>コンテンツ管理</li>
          <li>編集</li>
        </ol> 
        <Filter @search="handleSearch"></Filter>
        <div class="col-lg-12">
          <div class="card">
              <div class="contents_head">
                <h5 class="card-title">コンテンツ一覧</h5>
              </div>          
              <div class="scroll-box s scroll-box_y d">
                <table class="table_w tablesorter alter" id="table_sort">   
                  <thead>
                    <tr class="head">
                      <th class="narrow_a"@click="sort('更新日時')">投稿日
                        <span v-if="sortColumn === '更新日時' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '更新日時' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_h"@click="sort('ジャンル')">ジャンル
                        <span v-if="sortColumn === 'ジャンル' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === 'ジャンル' && sortOrder === 'desc'">▼</span>
                      </th>     
                      <th class="narrow_f"@click="sort('タイトル')">タイトル
                        <span v-if="sortColumn === 'タイトル' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === 'タイトル' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_f"@click="sort('品名CD')">品名CD
                        <span v-if="sortColumn === '品名CD' && sortOrder === 'asc'">▲</span>
                        <span v-if="sortColumn === '品名CD' && sortOrder === 'desc'">▼</span>
                      </th>
                      <th class="narrow_e"></th>
                      <th class="narrow_e"></th>
                      <th class=""></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in items" :key="index">
                    <th class="item_f">{{ formatDate(item.更新日時) }}</th>
                    <td data-label="ジャンル">{{ item.ジャンル }}</td>  
                    <td data-label="タイトル" class="txt">{{ item.タイトル }}</td>  
                    <td data-label="品名CD" class="txt">{{ item.品名CD }}</td>                  
                    <td class="sp_btn">
                      <button @click.prevent="goToDetail(item.品名CD)" type="button" class="bo_btn se">詳細</button>
                    </td>
                    <td class="sp_btn">
                      <button @click.prevent="goToEdit(item.品名CD)" type="button" class="bo_btn">編集</button>
                    </td>
                    <td class="sp_btn"><div class="bo_btn mi delete_btn"@click.prevent="updateRef.open(item.Mコンテンツ_ID)">削除</div></td>
                    
                  </tr>
                </tbody>                             
                </table>
              </div>
          </div>
        </div>
          <div class="table_fot" v-if="itemsTotal > 0">
          <span v-if="itemsTotal === 0">
            全 0 件
          </span>
          <span v-else>
            全 {{ itemsTotal }} 件中
            {{ (page - 1) * pageSize + 1 }} 件 〜
            {{ Math.min(page * pageSize, itemsTotal) }} 件を表示
          </span>
        
        <!-- ページネーションの修正: 1ページあたりの件数を指定 -->
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
    <Update ref="updateRef"@reLoad="reLoadItems"></Update>
</template>
