<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';
import { useRouter } from 'vue-router';

defineProps({
  authItems: Array
})

const router = useRouter();
const items = ref([]);
const itemsTotal = ref(0);
const page = ref(1);
const pageSize = ref(17); 
const loading = ref(false);
const currentFilters = ref({});
const sortColumn = ref('送信日時');
const sortOrder = ref('desc');
const userId = ref(null); // userIdをrefで定義

const savedFilters = sessionStorage.getItem('messageFilters');
if (savedFilters) {
  currentFilters.value = JSON.parse(savedFilters);
}

// セッションから担当者CDを取得
const getUserId = async () => {
  try {
    const response = await axios.get('/api/auth/getUserId');
    userId.value = response.data.userId; // userIdを設定
    // console.log("取得した担当者コード:", userId.value);
  } catch (error) {
    console.error("担当者コード取得中にエラーが発生しました:", error);
  }
};

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

const reLoadItems = () => {
  loading.value = true;
  const params = {
    ...currentFilters.value,
    page: page.value,
    pageSize: pageSize.value,
    sortColumn: sortColumn.value,
    sortOrder: sortOrder.value,
    userId: userId.value, // userIdをparamsに含める
  };
  axios
    .get('/api/message/list', { params })
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

onMounted(async () => {
  await getUserId(); // userIdを取得
  reLoadItems();
});

const handleSearch = (params) => {
  currentFilters.value = params;
  sessionStorage.setItem('messageFilters', JSON.stringify(params));
  page.value = 1;
  reLoadItems();
};

const goToDetail = (messageCode) => {
  router.push({ path: '/message/detail', query: { key: messageCode } });
};

const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD');
};

import Filter from '@/Pages/Message/Filter.vue';
</script>


<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>メッセージ送受信</li>
      </ol> 
      <Filter @search="handleSearch"></Filter>
      <div class="col-lg-12">          
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">メッセージ一覧</h5>
          </div>                     
          <div class="scroll-box s scroll-box_y c">
            <table class="tablesorter table_w send time" id="table_sort">
              <thead>
                <tr class="head">
                  <th class="sm_s right narrow_b" @click="sort('送信日時')">日付
                    <span v-if="sortColumn === '送信日時' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '送信日時' && sortOrder === 'desc'">▼</span>
                  </th>
                  <th class="narrow_b" @click="sort('送信者名')">担当者名
                    <span v-if="sortColumn === '送信者名' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '送信者名' && sortOrder === 'desc'">▼</span>
                  </th>
                  

                  <th class="narrow_b" @click="sort('送信者所属名_社内用')">所属
                    <span v-if="sortColumn === '送信者所属名_社内用' && sortOrder === 'asc'">▲</span>
                    <span v-if="sortColumn === '送信者所属名_社内用' && sortOrder === 'desc'">▼</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in items" :key="index">
                  <th class="item_f p mark_m">
                    <div v-if="item.送信者CD !== userId && item.HMステータス === 1" class="unread"></div>
                    <i v-if="item.送信者CD === userId && item.メッセージ !== '追加されました'" class="fa-solid fa-reply"></i>
                    {{ formatDate(item.送信日時) }}
                  </th>
                  <td data-label="担当者名">
                    {{ item.送信者CD === userId ? item.受信者名 : item.送信者名 }}
                  </td>
                  
                  <td data-label="所属">
                    {{ item.送信者CD === userId ? item.受信者所属名_社内用 : item.送信者所属名_社内用 }}
                  </td>
                  <td class="sp_btn">
                    <button 
                      @click.prevent="goToDetail(item.メッセージCD)" 
                      type="button" 
                      class="button_r none search"
                      :class="{ mi: item.送信者CD === userId && item.メッセージ === '追加されました' }"
                    >表示</button>
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
