<template>
  <!-- モーダルラップ -->
  <div class="modal_wrap" v-if="isVisible" id="delete_modal">
    <div class="modal_inner">
      <div class="close_icon" @click="close">
        <i class="fa-solid fa-user fa-lg icon_k"></i>
      </div>
      <div class="signup_form">
        <h5>追加したいユーザーを<br>選択してください。</h5>
      </div>
      <div class="scroll-box_y a">
        <!-- テーブルの作成 -->
        <table class="tb_kengen td_user">
          <thead>
          <tr>
            <th class="lt">担当者CD</th>
            <th class="">担当者名</th>
            <th class="mt">追加</th>
          </tr>
        </thead>
        <tbody>
          <!-- 類似担当者リストのループ表示 -->
          <tr v-for="staff in similarStaffList" :key="staff.担当者CD">
              <td>{{ staff.担当者CD }}</td>
              <td>{{ staff.担当者名 }}</td>
              <td>
                <input
                  class="form-check-input"
                  type="checkbox"
                  v-model="staffSelections[staff.担当者CD]"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="btn_space_modal">
        <div class="submit_btn_no close_icon" @click="close">キャンセル</div>
        <div class="submit_btn_yes id_btn_yes" @click="save">追加</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

// モーダル表示状態の管理
const isVisible = ref(false);

// 類似担当者リストを初期化
const similarStaffList = ref([]);

// 担当者の選択状態を管理
const staffSelections = ref([]);


// モーダルを開く処理
const open = (staffList) => {
  similarStaffList.value = staffList || [];
  // 選択状態をリセット
  staffSelections.value = Array(staffList.length).fill(false);
  isVisible.value = true;
};

// モーダルを閉じる処理
const close = () => {
  isVisible.value = false;
};
function generateRandomCode(length = 10) {
  const characters = '0123456789';
  let result = '';
  const charactersLength = characters.length;
  for (let i = 0; i < length; i++) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }
  return result;
}
// 保存処理
const save = async () => {
  const selectedStaff = similarStaffList.value.filter(staff => staffSelections.value[staff.担当者CD]);

  if (selectedStaff.length === 0) {
    
    ElNotification({
          title: 'Error',
          message: '担当者を選択してください。',
          type: 'error',
      });
    return;
  }

  const requestData = {
    staff: selectedStaff.map(staff => ({
      messagecode: generateRandomCode(),
      sendecode: staff.担当者CD,
      Description: '追加されました'
    }))
  };

  try {
    const response = await axios.post('/api/message/newcreate', requestData);
    ElNotification({
          title: 'Success',
          message: '担当者が追加されました。',
          type: 'success',
      });
    setTimeout(() => {
      window.location.reload();
    }, 1000);
    close();
  } catch (error) {
    if (error.response && error.response.status === 409) {
      ElNotification({
          title: 'Already Exists',
          message: 'すでにメッセージが存在します。',
          type: 'error',
      });
    } else if (error.response && error.response.data.errors) {
      console.error('バリデーションエラー:', error.response.data.errors);
      alert('エラー: ' + JSON.stringify(error.response.data.errors));
    } else {
      console.error('エラーが発生しました:', error);
    }
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
  background: #fff;
  width: 90%; /* ウィンドウの90%に調整 */
  max-width: 500px; /* 最大幅を設定 */
  max-height: 80%; /* 最大高さを80%に調整 */
  overflow-y: auto; /* 高さを超えた場合にスクロール可能にする */
  margin: 0 auto;
  text-align: center;
  padding: 20px;
  border-radius: 5px;
  position: absolute;
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
