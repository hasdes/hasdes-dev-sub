<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

const emit = defineEmits(['reLoad']);

//親コンポーネント(Edit.vue)から受け取る
const props = defineProps({
  customerId: { //M出荷先_ID（クエリ用）
    type: Number,
    required: true
  }
});

// モーダル表示状態の管理
const isVisible = ref(false);
const id = ref(null);

// モーダルを開く処理（選択したデータを受け取る）
const open = (dId) => {
  console.log("HD変換ID:", dId);
  isVisible.value = true;
  id.value = dId;
};


// モーダルを閉じる処理
const close = () => {
  isVisible.value = false;
  id.value = null;
};

// 選択データを削除する処理
const updateConver = async () => {
  try {
    if (!id.value) {
      ElNotification({ title: 'Error', message: 'データが選択されていません。', type: 'error'});
      return;
    }

    //削除
    const response = await axios.put('/api/convershipping/update', { id: id.value });

    //削除成功
    if (response.data.success) {
      ElNotification({ title: 'Success', message: '削除完了しました。', type: 'success' }); //ポップ表示

      // 現在のクエリパラメータを保持してページ遷移
      emit('reLoad'); // 親コンポーネントへ通知して一覧を更新
      close();//モーダル閉じる

    } else {
      ElNotification({ title: 'Error', message: response.data.message, type: 'error' });
    }
  } catch (error) {
    console.error(error);  
    const errorMessage = error?.response?.data?.message || 'データの削除に失敗しました。';    
    ElNotification({title: 'Error', message: errorMessage, type: 'error' });
  }
};

// 外部から呼び出せる関数を定義
defineExpose({ open });
</script>


<template>

<div class="modal_wrap" id="delete_modal" v-if="isVisible">
  <div class="modal_inner s">
    <div class="close_icon">
      <i class="fa-solid fa-trash-can fa-lg icon_k"></i>
    </div>
    <div class="signup_form">
      <h5>選択した情報を削除します。<br>
        よろしいですか？</h5>
    </div>
      <div class="btn_space_modal">
        <div class="submit_btn_no close_icon" @click="close">キャンセル</div>
        <div class="submit_btn_yes id_btn_yes" @click.prevent="updateConver">削除</div>
      </div>          
  </div>
</div>  
</template>


<style scoped>
/* 必要に応じてスタイルを調整してください */
.modal_wrap{
  display: flex;
  justify-content: center;
  align-items: center;
  position: fixed;
  top:0;
  right:0;
  bottom:0;
  left:0;
  background-color: rgba(0, 0, 0, 0.6);
  z-index: 1000;
}
/**ログインボタン押した後に出てくるモーダル**/
.modal_inner {
  background: #fff;
  width: 90%; /* ウィンドウの90%に調整 */
  max-width: 500px; /* 最大幅を設定 */
  max-height: 80%; /* 最大高さを80%に調整 */
  overflow-y: auto; /* 高さを超えた場合にスクロール可能にする */
  margin: 0 auto;
  text-align: center;
  padding: 20px;
  border-radius: 5px;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
}

.scroll-box_y.a {
    height: 20rem;
  }
.tb_kengen th,
.tb_kengen td{
  padding: 12px;
  box-sizing:border-box;
  font-weight: normal;
  font-size: 12px;
}
.tb_kengen th {
  background: #194795;
  color: #fff; 
}
.tb_kengen td {
  display: flex;
  justify-content: space-between;
}
.tb_kengen tr td:nth-child(2n) {
  /* 偶数行のスタイルを指定 */
  border-top: solid 1px #B1B1B1;
  border-right: solid 1px #B1B1B1;
}
.tb_kengen tr td:nth-child(2n + 1) {
  /* 奇数行のスタイルを指定 */
  border-right: solid 1px #B1B1B1;
}
.tb_kengen td.mt,.td_user th.mt {
  border-top-right-radius: 3px;
}
.tb_kengen th.lt {
  border-top-left-radius: 3px;
}
.tb_kengen td.mb,.td_user td.mb {
  border-bottom-right-radius: 3px;
}
.tb_kengen th.lb,.td_user td.lb {
  border-bottom-left-radius: 3px;
}
.bm {
  border-bottom: solid 1px #B1B1B1;
}
.tb_kengen.td_user td {
  display: table-cell;
}
.tb_kengen.td_user tr td {
  border-bottom: solid 1px #B1B1B1;
  border-right: 0;
}
.tb_kengen.td_user tr td:nth-child(2n + 1) {
  border-right:0;
}
.tb_kengen.td_user tr td:nth-child(1) {
  border-left: solid 1px #B1B1B1;
}
.tb_kengen.td_user tr td:nth-child(3) {
  border-right: solid 1px #B1B1B1;
}

</style>
