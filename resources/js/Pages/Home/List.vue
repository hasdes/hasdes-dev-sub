<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';

import { useRouter } from 'vue-router'; // Vue Routerのインポート

const router = useRouter(); // ルーターインスタンスの取得

const items = ref([]);
const itemsTotal = ref(0);

const anotherItems = ref([]);
const anotherItemsTotal = ref(0);

const itemsPage = ref(1); // itemsのページ番号
const anotherItemsPage = ref(1); // anotherItemsのページ番号

const pageSize = ref(10);
const anotherpageSize = ref(3);

const loading = ref(false);

const currentFilters = ref({});
const anothercurrentFilters = ref({}); // 現在の検索条件
const userId = ref(null);

const getUserId = async () => {
  try {
    const response = await axios.get('/api/auth/getUserId');
    userId.value = response.data.userId; // userIdを設定
    // console.log("取得した担当者コード:", userId.value);
  } catch (error) {
    console.error("担当者コード取得中にエラーが発生しました:", error);
  }
};

const setItemsPage = (val) => {
  itemsPage.value = val;
  reLoadItems();
};

const setAnotherItemsPage = (val) => {
  anotherItemsPage.value = val;
  reLoadAnotherItems();
};

const reLoadItems = () => {
  loading.value = true;
  const params = {
    ...currentFilters.value, // 現在の検索条件を展開
    page: itemsPage.value,
    pageSize: pageSize.value,
    userId: userId.value,
  };
  axios.get('/api/home/Messagelist', { params})
    .then((res) => {
      items.value = res.data.data;
      itemsTotal.value = res.data.total;
    })
    .finally(() => {
      loading.value = false;
    });
};

const reLoadAnotherItems = () => {
  loading.value = true;
  const params = {
    ...anothercurrentFilters.value, // 現在の検索条件を展開
    page: anotherItemsPage.value,
    anotherpageSize:anotherpageSize.value,
  };
  axios.get('/api/home/Contentslist', { params })
    .then((res) => {
      anotherItems.value = res.data.data;
      anotherItemsTotal.value = res.data.total;
      
    })
    .finally(() => {
      loading.value = false;
    });
};



const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD');
};

const formatDatebetu = (dateString) => {
  return dayjs(dateString).format('YYYY年MM月DD日');
};

const goToDetail = (messageCode) => {
  router.push({ path: '/message/detail', query: { key: messageCode } });
};
const goToContentsDetail = (messageCode) => {
  router.push({ path: '/item/ContentsDetail', query: { key: messageCode } });
};

onMounted(() => {
  getUserId();
  reLoadItems();
  reLoadAnotherItems();
});

</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">メッセージ一覧</h5>
          </div>
          <div class="scroll-box s scroll-box_y a">
            <table class="table_w time">
              <thead>
              <tr class="head">
                <th class="sm_s"></th>
                
                <th class="narrow_b">担当者名</th>
                <th>内容</th>
                <th class="sm"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="items.length === 0">
                <td colspan="5" class="no-messages">新着メッセージはありません</td>
              </tr>
              <tr v-else v-for="(item, index) in items" :key="index">
                <th class="item_f p mark_m" data-label="送信日時">
                  <div v-if="item.送信者CD !== userId && item.HMステータス === 1" class="unread"></div>
                  <i v-if="item.送信者CD === userId && item.メッセージ !== '追加されました'" class="fa-solid fa-reply"></i>
                  {{ formatDate(item.送信日時) }}
                </th>
                
                <td data-label="送信者名">{{ item.送信者CD === userId ? item.受信者名 : item.送信者名 }}</td>
                <td data-label="メッセージ" class="txt">{{ item.メッセージ }}</td>
                <td class="sp_btn">
                  <button 
                      @click.prevent="goToDetail(item.メッセージCD)" 
                      type="button" 
                      class="bo_btn"
                      :class="{ mi: item.送信者CD === userId && item.メッセージ === '追加されました' }">
                    表示
                  </button>
                </td>
              </tr>

            </tbody>

            </table>
          </div>
        </div>
      </div>
      <div class="table_fot" v-if="itemsTotal > 0">
        <span >
          全 {{ itemsTotal }} 件中 
          {{ (itemsPage - 1) * pageSize + 1 }} 件 〜 
          {{ Math.min(itemsPage * pageSize, itemsTotal) }} 件を表示
        </span>
        <!-- ページネーションの修正: 1ページあたりの件数を指定 -->
        <el-pagination
          layout="prev, pager, next"
          :total="itemsTotal"
          :page-size="pageSize"
          :current-page.sync="itemsPage"
          @current-change="setItemsPage"
        ></el-pagination>
      </div>

      <div class="col-lg-12">
        <div class="row between last">
          <div class="col-md-4" v-for="(anotherItem, index) in anotherItems" :key="index">
            <div class="card">
              <div class="contents_head">
                <h5 class="card-title">{{ formatDatebetu(anotherItem.投稿日時) }}</h5>
              </div>
              <div class="card-body top_box">
                <h2>{{ anotherItem.タイトル }}</h2>
                <div class="box_border">
                  <p>{{ anotherItem.詳細 }}</p>
                  <a @click.prevent="goToContentsDetail(anotherItem.品名CD)" type="button">…もっと読む</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="table_fot" v-if="anotherItemsTotal > 0">

        <span>
          全 {{ anotherItemsTotal }} 件中 
          {{ (anotherItemsPage - 1) * anotherpageSize + 1 }} 件 〜 
          {{ Math.min(anotherItemsPage * anotherpageSize, anotherItemsTotal) }} 件を表示
        </span>
        
        <!-- ページネーションの修正: 1ページあたりの件数を指定 -->
        <el-pagination
          layout="prev, pager, next"
          :total="anotherItemsTotal"
          :page-size="anotherpageSize"
          :current-page.sync="anotherItemsPage"
          @current-change="setAnotherItemsPage"
        ></el-pagination>
      </div>
    </div>
  </section>
</template>
