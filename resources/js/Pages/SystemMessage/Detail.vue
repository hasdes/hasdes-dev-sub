<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

defineProps({
  authItems: Array
})

const items = ref([]);
const key = ref(null);
const scrollContainer = ref(null); // スクロールコンテナをrefとして定義
const initialSenderCD = ref(null); // 初めに出現する送信者CD
const initialReceiverCD = ref(null); // 初めに出現する受信者CD
const searchKeyword = ref('');

// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key');
};

// スクロールを一番下に移動する関数
const scrollToBottom = () => {
  const container = scrollContainer.value;
  if (container) {
    container.scrollTop = container.scrollHeight;
  }
};

// 顧客情報を取得する関数
const reLoadItems = () => {
  key.value = getKeyFromUrl();
  axios.get('/api/systemmessage/detail', {
      params: { key: key.value }
    })
    .then((res) => {
      items.value = res.data.data;
      setTimeout(scrollToBottom, 100);
      // 最初の送信者CDと受信者CDを記録
      if (items.value.length > 0) {
        initialSenderCD.value = items.value[0].送信者CD;
        initialReceiverCD.value = items.value[0].受信者CD;
      }
    })
    .catch((error) => {
      console.error(error);
    });
};

onMounted(() => {
  reLoadItems();
});

const highlightMessages = () => {
  if (searchKeyword.value.trim() === '') return;

  items.value = items.value.map(item => {
    const regex = new RegExp(`(${searchKeyword.value})`, 'gi');
    const highlightedMessage = item.メッセージ.replace(regex, '<mark>$1</mark>');
    return {
      ...item,
      highlightedMessage, // ハイライトされたメッセージを新しく追加
    };
  });
};
</script>

<template>
  <section class="section dashboard">
    <div class="row send_page justify-content-between">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li><router-link to="/systemmessage">メッセージ送受信</router-link></li>
        <li v-if="items.length > 0">{{ items[0].送信者名 }} - {{ items[0].受信者名 }}</li>
      </ol>

      <div class="col-md-6">
        <div class="card">
          <details class="contents_head">
            <summary class="send_f">
              <h5 class="card-title" v-if="items.length > 0">
                {{ items[0].送信者名 }} - {{ items[0].受信者名 }}
              </h5>

              <i class="none"></i>
              <i class="fa-solid fa-magnifying-glass"></i>
            </summary>
            <form action="">
              <div class="form_r search_send send_f">
                <input type="text" class="form-control normal" v-model="searchKeyword" />
                <button type="submit" class="button_icon" @click.prevent="highlightMessages">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </button>
              </div>
            </form>
          </details>
          <div class="room scroll-box_y e" ref="scrollContainer">
            <ul>
              <li
                v-for="(item, index) in items"
                :key="index"
                :class="{
                  chat: true,
                  me: item.送信者CD === initialSenderCD || item.受信者CD === initialReceiverCD,
                  you: item.送信者CD !== initialSenderCD && item.受信者CD !== initialReceiverCD
                }"
              >
                <p class="mes" v-html="item.highlightedMessage || item.メッセージ"></p> <!-- ハイライトが適用されるメッセージを表示 -->
                <div class="status">{{ item.送信日時 }}</div>
              </li>
            </ul>
          </div>
        </div>
            <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
            </div>
      </div>
    </div>
  </section>
</template>
