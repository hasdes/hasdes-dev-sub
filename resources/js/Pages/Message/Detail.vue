<script setup>
import { onMounted, ref, reactive } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';
import { trim } from 'lodash';
import dayjs from 'dayjs';

defineProps({
  authItems: Array
})

const items = ref([]);
const userId = ref(null);
const key = ref(null);
const scrollContainer = ref(null); // スクロールコンテナをrefとして定義
const receptionName = ref(''); // 相手の名前を格納するためのref
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
  if (key.value) {
    axios.get('/api/message/detail', {
        params: { key: key.value }
      })
      .then((res) => {
        userId.value = res.data.userId;
        if (Array.isArray(res.data.data)) {
          items.value = res.data.data;
        } else {
          items.value = [res.data.data];
        }
        // メッセージが読み込まれた後にスクロールを一番下に移動
        setTimeout(scrollToBottom, 100);
        setReception(res.data.otherStaffNames); // 受信者の名前をセット
      })
      .catch((error) => {
        console.error(error);
      });
  } else {
    console.error('URLにkeyパラメータがありません。');
  }
};

onMounted(() => {
  reLoadItems();
});

// フォームの定義
const form = reactive({
  Description: '',
  messagecode: key,
  sendecode: userId,
  reception: null,
});

// receptionの値を設定する関数
const setReception = (otherStaffNames) => {
  if (items.value.length > 0) {
    const firstItem = items.value[0];
    if (userId.value === firstItem.受信者CD) {
      form.reception = firstItem.送信者CD;
    } else {
      form.reception = firstItem.受信者CD;
    }
    // receptionの名前を取得
    receptionName.value = otherStaffNames[0] || '相手の名前';
  }
};

// バリデーションの関数
const validate = () => {
  if (trim(form.Description) === '') {
    ElNotification({
      title: 'Error',
      message: '文章は省略できません',
      type: 'error',
    });
    return false;
  }
  return true;
};

// 作成関数
const create = () => {
  if (!validate()) return;

  axios.post('/api/message/create', form)
    .then(() => {
      ElNotification({
        title: 'Success',
        message: '登録成功しました',
        type: 'success',
      });

      setTimeout(() => {
        window.location.reload();
      }, 1000);
    })
    .catch((error) => {
      console.error('error ' + error);
      ElNotification({
        title: 'Error',
        message: '登録に失敗しました',
        type: 'error',
      });
    });
};
const formatDate = (dateString) => {
  return dayjs(dateString).format('YYYY/MM/DD');
};

// 改行を<br>タグに変換する関数
const nl2br = (str) => {
  if (!str) return '';
  return str.replace(/\n/g, '<br>');
};

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
        <li><router-link to="/message">メッセージ送受信</router-link></li>
        <li>{{ receptionName }}</li>
      </ol>

      <div class="col-md-6">
        <div class="card">
          <details class="contents_head">
            <summary class="send_f">
              <h5 class="card-title">{{ receptionName }}</h5>
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
                :class="userId === item.送信者CD ? 'chat me' : userId === item.受信者CD ? 'chat you' : ''"
              >
                <p class="mes" v-html="nl2br(item.highlightedMessage || item.メッセージ)"></p> <!-- v-htmlとnl2brを使用 -->
                <div class="status">{{ formatDate(item.送信日時) }}</div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">メッセージ内容</h5>
          </div>

          <el-form :model="form">
            <div class="row space align-center two">
              <div class="col-lg-12 form_r s">
                <textarea
                  v-model="form.Description"
                  class="form-control normal"
                  style="height: 410px"
                ></textarea>
              </div>
              <div class="col-lg-12 flex_end">
                <button type="button" @click="create" class="button_r none search">送信</button>
              </div>
            </div>
          </el-form>
          
        </div>
        
      </div>
    </div>
    <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
            </div>
  </section>
</template>
