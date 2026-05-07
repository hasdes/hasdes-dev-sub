<script setup>
import { reactive, onMounted } from 'vue'; 
import axios from 'axios';
import { ElNotification } from 'element-plus';
const emit = defineEmits(['reLoad']); // イベント宣言


// 親コンポーネント(Detail.vue)から受け取る
const props = defineProps({
  //商品CD
  productCd: {
    type: String,
    required: true
  },
  // //M商品_ID
  // productId: {
  //   type: Number,
  //   required: true
  // }
});


const form = reactive({ 商品: '', 変換名: '' });

// onMounted(() => {
//   const logData = { '実行内容': '商品変換:新規追加画面表示' };

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

//新規追加
const create = () => {
  if (!validate()) return;

  const formData = new FormData();
  // formData.append('M商品_ID', props.productId);//親から渡された値
  formData.append('商品CD', props.productCd);//親から渡された値
  formData.append('変換名', form.変換名);
  
  axios.post('/api/converproduct/create', formData)
  .then(() => {
    ElNotification({
      title: 'Success',
      message: '登録成功しました',
      type: 'success',
    });
    
    form.変換名 = ''; // 入力欄をクリア
    emit('reLoad'); // 親コンポーネントに一覧を更新通知
  })
    .catch((error) => {
      let errorMessage = '登録に失敗しました';
      if (error.response) {
        if (error.response.status === 400) {
          errorMessage = '指定された商品CDは存在しません。';
        } else if (error.response.status === 409) {
          errorMessage = '入力された変換名はすでに登録されています。';
        }
      }
      ElNotification({ title: 'Error', message: errorMessage, type: 'error' });
    });
};

</script>



<template>
  <div class="col-lg-12">
    <div class="card">
      <details class="contents_head" open>
        <summary class="send_f">
          <h5 class="card-title">変換パターン</h5>
          <i class="bi bi-arrow-up"></i>
        </summary>
        <el-form :model="form" @submit.prevent>
          <div class="search_send bo_none">
            <div class="row align-center two space_c">                  
              <div class="col-lg-3 form_r flex">
                <input type="text" class="form-control normal" v-model="form.変換名">                    
              </div>                   
              <div class="col-lg-1 btn_center center_a">
                <button type="button" @click="create" class="button_r none search">新規追加</button>        
              </div>
            </div>             
          </div>
        </el-form>        
    </details>  
    </div>
  </div>
</template>
