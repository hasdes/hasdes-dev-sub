<script setup>
import { reactive, onMounted } from 'vue'; 
import axios from 'axios';
import { ElNotification } from 'element-plus';
const emit = defineEmits(['reLoad']); // イベント宣言


// 親コンポーネント(Detail.vue)から受け取る
const props = defineProps({
  //CD
  cd: {
    type: Number,
    required: true
  },
  //管轄部門CD
  jurisdiction: {
    type: Number,
    required: true
  },
  type: {
    type: String,
    required: true,
    validator: (val) => ['customer', 'shipping', 'product'].includes(val)
  }

});

// console.log('管轄部門：',props.jurisdiction);
const form = reactive({ 得意先CD: '', 変換名: '' });

// onMounted(() => {
//   const logData = { '実行内容': '得意先変換:新規追加画面表示' };

//   axios.post('/api/HDLog/create', logData)
//     .then(() => console.log('ログが正常に保存されました'))
//     .catch((error) => console.error('ログ保存中にエラーが発生しました', error));
// });

//バリデーション
const validate = () => {
  if (!form.変換名) {
      ElNotification({ title: 'Error', message: '変換名を入力してください。', type: 'error' });
      return false; // エラーがあればここで終了
  }
  // すべての条件が満たされた場合
  return true;
};

//新規追加 ----------------------------------------------------------------
const create = () => {
  if (!validate()) return;

  // 各タイプ別のURLとラベル
  let listUrl = '';
  let label = '';
  let createUrl = '';

  if (props.type === 'customer') {
    // listUrl = '/api/convercustomer/list';
    label = '得意先CD';
    createUrl = '/api/convercustomer/create';
  } else if (props.type === 'shipping') {
    // listUrl = '/api/convershipping/list';
    label = '出荷先_エンドユーザーCD';
    createUrl = '/api/convershipping/create';
  } else if (props.type === 'product') {
    // listUrl = '/api/converproduct/list';
    label = '商品CD';
    createUrl = '/api/converproduct/create';
  }

  // 存在チェック
  // axios.get(listUrl, {
  //   params: { 
  //     key: props.cd, 
  //     key2: props.jurisdiction 
  //   }
  // })
  // .then(() => {
  //   // チェックOKならPOST
  //   const formData = new FormData();
  //   formData.append(label, props.cd);//CD
  //   formData.append('管轄部門CD', props.jurisdiction);//管轄部門
  //   formData.append('変換名', form.変換名);//変換名

  //   return axios.post(createUrl, formData);
  // })
  // .then(() => {
  //   ElNotification({
  //     title: 'Success',
  //     message: '登録成功しました',
  //     type: 'success',
  //   });
  //   form.変換名 = '';
  //   emit('reLoad');
  // })

  // FormData 作成
  const formData = new FormData();
  formData.append(label, props.cd); // CD
  formData.append('管轄部門CD', props.jurisdiction);
  formData.append('変換名', form.変換名);
  
  axios.post(createUrl, formData)
  .then(() => {
    ElNotification({
      title: 'Success',
      message: '登録成功しました',
      type: 'success',
    });
    form.変換名 = '';
    emit('reLoad');
  })

  .catch((error) => {
    let errorMessage = '登録に失敗しました';
    if (error.response) {
      if (error.response.status === 400) {
        errorMessage = `指定された${label}は存在しません。`;
      } else if (error.response.status === 409) {
        errorMessage = '入力された変換名はすでに登録されています。';
      }
    }
    ElNotification({ title: 'Error', message: errorMessage, type: 'error' });
  });
};
//----------------------------------------------------------------------------
</script>

<template>
<form @submit.prevent="create">
  <div class="d-flex gap-2 mb-3">
  <input 
    type="text" 
    class="form-control normal w-75" 
    placeholder="変換名を入力"
    maxlength="255"
    @keydown.enter.prevent
    v-model="form.変換名"
  />
    <button 
      type="button" 
      class="button_r none search cont od_a no w-25"
      @click="create"
    >
      追加
    </button>
  </div>
</form>
</template>
