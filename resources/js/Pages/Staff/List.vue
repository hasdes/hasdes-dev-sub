<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

defineProps({
  authItems: Array
})

const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); // 1ページあたりの表示件数
const loading = ref(false);

// 現在の検索条件を保持するオブジェクト
const currentFilters = ref({});

const savedFilters = sessionStorage.getItem('staffFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

// ソートに関する状態
const sortColumn = ref('担当者CD'); // 初期表示時のデフォルトソートカラム
const sortOrder = ref('asc'); // 初期表示時のデフォルトソート順


const PRODUCT_TYPES = {
  0: 'システム管理',
  1: '従業員',
  2: '退職者',
  3: 'その他',
};

const getProductTypeName = (productType) => {
  return PRODUCT_TYPES[productType] || '不明';
};

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
    pageSize: pageSize.value, // ページサイズをパラメータに追加
    sortColumn: sortColumn.value, // ソート対象カラム
    sortOrder: sortOrder.value, // ソート順
  };
  axios
    .get('/api/staff/list', { params })
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
  sessionStorage.setItem('staffFilters', JSON.stringify(params));
  page.value = 1; // ページをリセット
  reLoadItems();
};

import Edit from '@/Pages/Staff/Edit.vue'
const editRef = ref();

import Update from '@/Pages/Staff/Update.vue'
const updateRef = ref();

import Updatestatus from '@/Pages/Staff/Updatestatus.vue'
const updatestautsRef = ref();

import Rockstatus from '@/Pages/Staff/Rockstatus.vue'
const RockRef = ref();



import Filter from '@/Pages/Staff/Filter.vue';
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
          <li>従業員マスタメンテ</li>
        </ol>
        <Filter @search="handleSearch"></Filter>
        <div class="col-lg-12">
          <div class="card">
              <div class="contents_head">
                <h5 class="card-title">従業員情報</h5>
              </div>
              <div class="scroll-box s scroll-box_y d">
                  <table class="table_w tablesorter alter none btn_w sp_w " id="table_sort"> 
                    <thead>
                      <tr class="head">
                        <th class="narrow_b"@click="sort('担当者CD')">担当者CD
                          <span v-if="sortColumn === '担当者CD' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '担当者CD' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_b"@click="sort('担当者名')">担当者名
                          <span v-if="sortColumn === '担当者名' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '担当者名' && sortOrder === 'desc'">▼</span>
                        </th>     
                        <th class="narrow_b"@click="sort('部門略称名')">所属部門
                          <span v-if="sortColumn === '部門略称名' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '部門略称名' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_j"@click="sort('所属名_社内用')">所属
                          <span v-if="sortColumn === '所属名_社内用' && sortOrder === 'asc'">▲</span>
                          <span v-if="sortColumn === '所属名_社内用' && sortOrder === 'desc'">▼</span>
                        </th>
                        <th class="narrow_h">メールアドレス</th>         
                        <th class="sl sp_btn">権限</th>
                        <th class="sl sp_btn">パスワード</th>
                        <th class="sl sp_btn">従業員区分</th>
                        <th class="sl sp_btn">解除</th>
                      </tr>
                    </thead>
                    <tbody>
                    <tr
                      v-for="(item, index) in items"
                      :key="index"
                      :class="{ 'greyed-out': item.従業員区分 === 2 || item.従業員区分 === 3 }"
                    >
                      <th class="item_f" data-label="担当者CD">{{ item.担当者CD }}</th>
                      <td data-label="担当者名">{{ item.担当者名 }}</td>
                      <td data-label="部門略称名">{{ item.部門略称名 }}</td>
                      <td data-label="所属名_社内用">{{ item.所属名_社内用 }}</td>
                      <td data-label="EMAIL">{{ item.EMAIL }}</td>
                      <td class="sp_btn">
                        <button 
                          type="primary" 
                          @click.prevent="editRef.open(item.担当者CD)" 
                          class="button_r none search" 
                          :class="{ 'bo_btn syo delete_btn disabled': item.従業員区分 === 2 || item.従業員区分 === 3 }"
                          :disabled="item.従業員区分 === 2 || item.従業員区分 === 3"
                        >設定</button>
                      </td>
                      <td class="sp_btn">
                        <button 
                            class="button_r none search" 
                            :class="{ 'bo_btn syo delete_btn disabled': item.従業員区分 === 2 || item.従業員区分 === 3 }"
                            @click.prevent="item.従業員区分 === 2 || item.従業員区分 === 3 ? null : updateRef.open(item.担当者CD)"
                          >
                            初期化
                        </button>

                      </td>
                      <td class="sp_btn">
                        <button link type="primary" @click.prevent="updatestautsRef.open(item.担当者CD)" class="button_r none search">{{ getProductTypeName(item.従業員区分) }}</button>
                      </td>
                      <td class="sp_btn">
                        <div 
                          class="button_r none search" 
                          v-if="item.ロックカウント >= 5" 
                          @click.prevent="RockRef.open(item.担当者CD)" 
                          :class="{ 'bo_btn syo delete_btn disabled': item.従業員区分 === 2 || item.従業員区分 === 3 }"
                          :disabled="item.従業員区分 === 2 || item.従業員区分 === 3"
                        >
                          解除
                        </div>
                      </td>
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
    <Edit ref="editRef"@reLoad="reLoadItems"></Edit>
    <Update ref="updateRef"@reLoad="reLoadItems"></Update>
    <Updatestatus ref="updatestautsRef"@reLoad="reLoadItems"></Updatestatus>
    <Rockstatus ref="RockRef" @reLoad="reLoadItems"></Rockstatus>
</template>