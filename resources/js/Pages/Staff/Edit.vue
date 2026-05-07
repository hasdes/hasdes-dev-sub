<script setup>
import { ref } from 'vue';
import axios from 'axios';

import { onMounted, onUnmounted } from 'vue';
import { ElNotification } from 'element-plus';


// モーダル表示状態の管理
const isVisible = ref(false);

// 担当者CDを格納する変数
const selectedStaffCD = ref('');
const permissionsstaffname = ref('');

// 各チェックボックスの状態を管理
const ホーム = ref(false);
const メッセージ送受信 = ref(false);
const 在庫表示 = ref(false);
const 商品表示 = ref(false);
const 直送配車計画 = ref(false);
const 配車可_不可 = ref(false);
const 販売管理 = ref(false);
const 集荷処理= ref(false);
const 新規登録 = ref(false);
const 編集 = ref(false);
const 社内ヘルプデスク = ref(false);
const 従業員マスタメンテ = ref(false);
const 顧客企業マスタメンテ = ref(false);
const メッセージ管理 = ref(false);
const ログ管理 = ref(false);
const ユーザー設定 = ref(false);

// モーダルを開く処理
const open = async (staffCD) => {
  // モーダルを表示し、担当者CDを設定
  isVisible.value = true;
  selectedStaffCD.value = staffCD;

  try {
    // 権限情報を取得
    const response = await axios.get(`/api/staff/permissions/${staffCD}`);
    const permissions = response.data;

    permissionsstaffname.value = permissions['担当者名'];
    // 各チェックボックスの状態を取得したデータに基づいて再設定（0の時にチェックがつく）
    ホーム.value = permissions['ホーム'] === 0;
    メッセージ送受信.value = permissions['メッセージ送受信'] === 0;
    在庫表示.value = permissions['在庫表示'] === 0;
    商品表示.value = permissions['商品表示'] === 0;
    直送配車計画.value = permissions['直送配車計画'] === 0;
    配車可_不可.value = permissions['配車可_不可'] === 0;
    販売管理.value = permissions['販売管理'] === 0;
    集荷処理.value = permissions['集荷処理'] === 0;
    新規登録.value = permissions['新規登録'] === 0;
    編集.value = permissions['編集'] === 0;
    社内ヘルプデスク.value = permissions['社内ヘルプデスク'] === 0;
    従業員マスタメンテ.value = permissions['従業員マスタメンテ'] === 0;
    顧客企業マスタメンテ.value = permissions['顧客企業マスタメンテ'] === 0;
    メッセージ管理.value = permissions['メッセージ管理'] === 0;
    ログ管理.value = permissions['ログ管理'] === 0;
    ユーザー設定.value = permissions['ユーザー設定'] === 0;
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
        'ホーム': ホーム.value ? 0 : 1,
        'メッセージ送受信': メッセージ送受信.value ? 0 : 1,
        '在庫表示': 在庫表示.value ? 0 : 1,
        '商品表示': 商品表示.value ? 0 : 1,
        '直送配車計画': 直送配車計画.value ? 0 : 1,
        '配車可_不可': 配車可_不可.value ? 0 : 1,
        '販売管理': 販売管理.value ? 0 : 1,
        '集荷処理': 集荷処理.value ? 0 : 1,
        '新規登録': 新規登録.value ? 0 : 1,
        '編集': 編集.value ? 0 : 1,
        '社内ヘルプデスク': 社内ヘルプデスク.value ? 0 : 1,
        '従業員マスタメンテ': 従業員マスタメンテ.value ? 0 : 1,
        '顧客企業マスタメンテ': 顧客企業マスタメンテ.value ? 0 : 1,
        'メッセージ管理': メッセージ管理.value ? 0 : 1,
        'ログ管理': ログ管理.value ? 0 : 1,
        'ユーザー設定': ユーザー設定.value ? 0 : 1,
        // '担当者名': permissionsstaffname.value,
      }
    };

    const response = await axios.post('/api/staff/update', payload);

    if (response.status === 200) {
      ElNotification({
          title: 'Success',
          message: '権限が正常に更新されました',
          type: 'success',
      });
      close();
    }
  } catch (error) {
    console.error('権限の更新に失敗しました:', error);
    alert('権限の更新に失敗しました');
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
      <div class="close_icon">
        <i class="fa-solid fa-user-group fa-lg icon_k"></i>
      </div>
      <div class="signup_form">
        <h5>権限付与先を選択してください。</h5>
        <p>担当者CD: {{ selectedStaffCD }}</p>
        <p>名前:{{ permissionsstaffname}}</p>
      </div>
      <table class="tb_kengen">
        <tr>
          <th class="lt">ホーム</th>
          <td class="mt" rowspan="">
            <span>ホーム</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="ホーム">
          </td>
          <td></td> 
        </tr> 
        <tr>
          <th class="lt">メッセージ送受信</th>
          <td class="mt" rowspan="">
            <span>メッセージ送受信</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="メッセージ送受信">
          </td>
          <td></td> 
        </tr> 
        <tr>
          <th rowspan="3">情報表示</th>
          <td>
            <span>在庫表示</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="在庫表示">
          </td>
          <td></td> 
        </tr>
        <tr>
          <td>
            <span>商品表示</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="商品表示">
          </td>
          <td></td> 
        </tr>
        <tr>
          <td colspan="">
            <span>直送配車計画</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="直送配車計画">
          </td>
          <td>
            <span>配車可能/不可</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="配車可_不可">
          </td>
        </tr>
        <tr>
          <th>販売管理</th>
          <td>
            <span>販売管理</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="販売管理">
          </td>
          <td></td>
        </tr>
        <tr>
          <th>集荷処理</th>
          <td>
            <span>集荷処理</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="集荷処理">
          </td>
          <td></td>
        </tr>
        <tr>
          <th rowspan="2">コンテンツ管理</th>
          <td>
            <span>新規登録</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="新規登録">
          </td>
          <td></td>
        </tr>
        <tr>
          <td>
            <span>編集</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="編集">
          </td>
          <td></td>
        </tr>
        <tr>
          <th>社内ヘルプデスク</th>
          <td class="mt" rowspan="">
            <span>社内ヘルプデスク</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="社内ヘルプデスク">
          </td>
          <td></td> 
        </tr> 
        <tr>
          <th rowspan="4">システム管理</th>
          <td><span>従業員マスタメンテ</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="従業員マスタメンテ">
          </td>
          <td></td>
        </tr>
        <tr>
          <td><span>顧客企業マスタメンテ</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="顧客企業マスタメンテ">
          </td>
          <td></td>
        </tr>
        <tr>
          <td><span>メッセージ管理</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="メッセージ管理">
          </td>
          <td></td>
        </tr>
        <tr>
          <td><span>ログ管理</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="ログ管理">
          </td>
          <td></td>
        </tr>
        <tr>
          <th class="lb">ユーザー設定</th>
          <td class="mb bm">
            <span>ユーザー設定</span>
            <input class="form-check-input check_kengen" type="checkbox" v-model="ユーザー設定">
          </td>
          <td></td>
        </tr>      
      </table>
      <div class="btn_space_modal">
        <div class="submit_btn_no close_icon" @click="close">キャンセル</div>
        <div class="submit_btn_yes id_btn_yes" @click="save">完了</div>
      </div>          
    </div>
  </div>  
</template>
<style scoped>

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

</style>