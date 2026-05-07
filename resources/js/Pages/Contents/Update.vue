<template>
  <div class="modal_wrap" v-if="isVisible" id="delete_modal">
    <div class="modal_inner s">
      <div class="close_icon">
        <i class="fa-solid fa-lock fa-l icon_k p"></i>
      </div>
      <div class="signup_form">
        <h5>選択したコンテンツを削除します。<br>
          よろしいですか？</h5>
      </div>
      <div class="btn_space_modal">
        <div class="submit_btn_no close_icon" @click="close">キャンセル</div>
        <div class="submit_btn_yes id_btn_yes" @click="initializePassword">削除</div>
      </div>          
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

// モーダル表示状態の管理
const isVisible = ref(false);
const selectedContentsCD = ref(null);

// モーダルを開く処理
const open = (staffCD) => {
  isVisible.value = true;
  selectedContentsCD.value = staffCD;
};

// モーダルを閉じる処理
const close = () => {
  isVisible.value = false;
};

// パスワードを初期化する処理
const initializePassword = async () => {
  try {
    const response = await axios.post('/api/contents/update', {
      ContentsCD: selectedContentsCD.value,
    });
    if (response.data.success) {
      ElNotification({
          title: 'Success',
          message: '削除完了しました。',
          type: 'success',
      });
      setTimeout(() => {
      window.location.reload();
      }, 1000);
    } else {
      ElNotification({
          title: 'error',
          message: '削除に失敗しました。',
          type: 'error',
      });
    }
  } catch (error) {
    console.error(error);
    alert('エラーが発生しました。');
  }
};

// 外部から呼び出せる関数を定義
defineExpose({
  open,
});
</script>








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
  background:#fff;
  width: 500px;
  margin:0 auto;
  text-align: center;
  padding:20px;
  border-radius: 5px;
  position: absolute;
  top: 27rem;
  left: 50%;
  transform: translateY(-50%);
  transform:translateX(-50%);
  -webkit-transform: translateY(-50%) translateX(-50%);
  -ms-transform: translateY(-50%) translateX(-50%);
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
