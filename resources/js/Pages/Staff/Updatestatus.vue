<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';
// モーダル表示状態の管理
const isVisible = ref(false);

// 担当者CDを格納する変数
const selectedStaffCD = ref('');

// 各チェックボックスの状態を管理
const 従業員区分 = ref('');
const permissionsstaffname = ref('');

// モーダルを開く処理
const open = async (staffCD) => {
  // モーダルを表示し、担当者CDを設定
  isVisible.value = true;
  selectedStaffCD.value = staffCD;

  try {
    // 権限情報を取得
    const response = await axios.get(`/api/staff/getjyugyoinkubun/${staffCD}`);
    const permissions = response.data;

    permissionsstaffname.value = permissions['担当者名'];
    // 各チェックボックスの状態を取得したデータに基づいて再設定
    従業員区分.value = permissions['従業員区分'];
    
  } catch (error) {
    console.error('権限情報の取得に失敗しました:', error);
  }
};

// モーダルを閉じる処理
const close = () => {
  isVisible.value = false;
};

// 保存処理
const save = async () => {
  try {
    const payload = {
      '担当者CD': selectedStaffCD.value,
      '権限情報': {
        '従業員区分': 従業員区分.value,
      }
    };

    const response = await axios.post('/api/staff/updatejyugyoinkubun', payload);

    if (response.status === 200) {
      ElNotification({
          title: 'Success',
          message: '従業員区分が更新されました',
          type: 'success',
      });
    // setTimeout(() => {
    //   window.location.reload();
    // }, 1000);
    close();
    }
  } catch (error) {
    ElNotification({
          title: 'Error',
          message: '従業員区分の更新に失敗しました',
          type: 'error',
      });
  }
};

// 外部から呼び出せる関数を定義
defineExpose({
  open,
});
</script>

<template>
  <div class="modal_wrap" v-if="isVisible" id="kengen_modal">
    <div class="modal_inner">
      <div class="close_icon" @click="close">
        <i class="fa-solid fa-user-group fa-lg icon_k"></i>
      </div>
      <div class="signup_form">
        <h5>従業員区分を選択してください。</h5>
        <p>担当者CD: {{ selectedStaffCD }}</p>
        <p>名前:{{ permissionsstaffname}}</p>
      </div>
      
      <table class="tb_kengen">
        <tbody>
          <tr>
            <th rowspan="4">従業員区分</th>
            <td>
              <span>システム管理</span>
              <input class="form-check-input" type="radio" name="従業員区分" v-model="従業員区分" :value="0">
            </td>
          </tr>
          <tr>
            <td>
              <span>従業員</span>
              <input class="form-check-input" type="radio" name="従業員区分" v-model="従業員区分" :value="1">
            </td>
          </tr>
          <tr>
            <td>
              <span>退職者</span>
              <input class="form-check-input" type="radio" name="従業員区分" v-model="従業員区分" :value="2">
            </td>
          </tr>
          <tr>
            <td>
              <span>その他</span>
              <input class="form-check-input" type="radio" name="従業員区分" v-model="従業員区分" :value="3">
            </td>
          </tr>
        </tbody>
      </table>

      <div class="btn_space_modal">
        <div class="submit_btn_no close_icon" @click="close">キャンセル</div>
        <div class="submit_btn_yes id_btn_yes" @click="save">完了</div>
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