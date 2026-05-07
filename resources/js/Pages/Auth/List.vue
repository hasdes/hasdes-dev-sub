<script>
import { onMounted,ref } from 'vue';
import axios from 'axios';

onMounted(() => {
  const logData = { 
    '実行内容': 'ユーザ設定情報表示',
  };

  axios.post('/api/HDLog/create', logData)
    .then(() => {
      console.log('ログが正常に保存されました');
    })
    .catch((error) => {
      console.error('ログ保存中にエラーが発生しました', error);
    });
});

export default {
  data() {
    return {
      page: ref(1), // ページネーション用の値
      items: ref([]), // アイテムリスト
      itemsTotal: ref(0), // アイテムの総数
    };
  },
  methods: {
    reLoadItems(filter = '') {
      const params = { page: this.page };
      if (filter !== '') {
        params['filter'] = filter;
      }
      axios.get('/api/auth/list', {
        params: params
      })
      .then((res) => {
        this.items = res.data.data;
        this.itemsTotal = res.data.total;
      })
      .catch((error) => {
        console.error(error);
      });
    },
    goBack() {
      window.history.back(); // 戻るボタンの動作
    },
    changePassword() {
      this.$router.push({ name: 'auth.edit' }); // パスワード変更ページへの遷移
    }
  },
  mounted() {
    // 初回ロード時にデータを取得
    this.reLoadItems();
  }
};
</script>
<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="items?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>ユーザー設定</li>
      </ol>
      <div class="col-lg-12">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">ユーザー設定</h5>
          </div>
          <div class="row space align-center justify-content-between page">
            <div class="col-md-6">
              <label class="col-form-label">担当者CD</label>
              <span>{{ items[0]?.担当者CD || 'データがありません' }}</span>
            </div>
            <div class="col-md-6">
              <label class="col-form-label">所属部門</label>
              <span>{{ items[0]?.部門略称名 || 'データがありません' }}</span>
            </div>
            <div class="col-md-6">
              <label class="col-form-label">所属</label>
              <span>{{ items[0]?.所属名_社内用 || 'データがありません' }}</span>
            </div>
            <div class="col-md-6">
              <label class="col-form-label">担当者名</label>
              <span>{{ items[0]?.担当者名 || 'データがありません' }}</span>
            </div>
            <div class="col-md-6">
              <label class="col-form-label">パスワード</label>
              <span>⚫︎⚫︎⚫︎⚫︎⚫︎⚫︎⚫︎⚫︎</span>
            </div>
            <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
              <a href="#" class="button_r back none od_b" @click.prevent="goBack">戻る</a>
              <button @click="changePassword" class="button_r none search od_a syoki no">パスワード変更</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>


