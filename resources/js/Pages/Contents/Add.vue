<script setup>
import { ref, reactive, watch, onMounted } from 'vue'; 
import axios from 'axios';
import { ElNotification } from 'element-plus';

defineProps({
  authItems: Array
})

const form = reactive({
  
  品名CD: '',
  ジャンル: [],
  タイトル: '',
  詳細: '',
  YouTube動画リンク: [''], // デフォルトで1つのリンク入力欄を表示
  files: [''], // デフォルトで1つのファイル入力欄を表示
});

onMounted(() => {
  const logData = { 
    '実行内容': 'コンテンツ新規登録画面表示',
  };

  axios.post('/api/HDLog/create', logData)
    .then(() => {
      console.log('ログが正常に保存されました');
    })
    .catch((error) => {
      console.error('ログ保存中にエラーが発生しました', error);
    });
});

const validate = () => {
  if (!form.品名CD) {
    ElNotification({
      title: 'Error',
      message: '品名CDを入力してください。',
      type: 'error',
    });
    return false; // エラーがあればここで終了
  } else if (!form.タイトル) {
    ElNotification({
      title: 'Error',
      message: 'タイトルを入力してください。',
      type: 'error',
    });
    return false; // エラーがあればここで終了
  } else if (!form.詳細) {
    ElNotification({
      title: 'Error',
      message: '詳細を入力してください。',
      type: 'error',
    });
    return false; // エラーがあればここで終了
  } else if (form.ジャンル.length === 0) {
    ElNotification({
      title: 'Error',
      message: 'ジャンルを入力してください。',
      type: 'error',
    });
    return false; // エラーがあればここで終了
  }

  // すべての条件が満たされた場合
  return true;
};


const create = () => {
  if (!validate()) return;

  const formData = new FormData();
  const genreOrder = ['動画', '資料', 'テキスト'];
  form.ジャンル.sort((a, b) => genreOrder.indexOf(a) - genreOrder.indexOf(b));
  formData.append('品名CD', form.品名CD);
  formData.append('ジャンル', form.ジャンル.join(',')); 
  formData.append('タイトル', form.タイトル);
  formData.append('詳細', form.詳細);
  
  // filesをfiles[]として送信
  form.files.forEach((file, index) => {
    if (file) formData.append('files[]', file); // 修正箇所
  });

  // YouTube動画リンクをYouTube動画リンク[]として送信
  form.YouTube動画リンク.forEach((link) => {
    formData.append('YouTube動画リンク[]', link); // 修正箇所
  });

  axios.post('/api/contents/create', formData)
    .then((res) => {
      ElNotification({
        title: 'Success',
        message: '登録成功しました',
        type: 'success',
      });
      setTimeout(() => {
        window.location.href = '/contents';
      }, 1000); 
    })
    .catch((error) => {
    console.log('error ' + error);

    let errorMessage = '登録に失敗しました'; // デフォルトのエラーメッセージ
    if (error.response) {
        if (error.response.status === 400) {
            errorMessage = '指定された品名CDは存在しません。'; // 400エラー用のメッセージ
        }else if (error.response.status === 401) {
            // 他のエラーメッセージがあればそれを使用
            errorMessage = '指定された品名CDはすでにコンテンツ登録されております。';
        }
    }

    ElNotification({
        title: 'Error',
        message: errorMessage,
        type: 'error',
    });
});

};

// 以下はその他の関数です（変更なし）
const addFileInput = () => {
  form.files.push(null);
};

const removeFile = (index) => {
  if (form.files.length > 1) {
    form.files.splice(index, 1);
  }
};

const updateFile = (event, index) => {
  form.files[index] = event.target.files[0];
};

const addYouTubeLinkInput = () => {
  form.YouTube動画リンク.push('');
};

const removeYouTubeLink = (index) => {
  if (form.YouTube動画リンク.length > 1) {
    form.YouTube動画リンク.splice(index, 1);
  }
};

const suggestions = ref([]); // 予測検索の候補リスト

// 品名CDの入力値を監視して予測検索
watch(() => form.品名CD, async (newVal) => {
  if (newVal) {
    try {
      const response = await axios.get('/api/hinmei/search', { params: { query: newVal } });
      suggestions.value = response.data; // サーバーから取得した候補リスト
      
    } catch (error) {
      console.error('候補の取得中にエラーが発生しました', error);
    }
  } else {
    suggestions.value = []; // 入力が空の場合は候補リストをリセット
  }
});

// 候補リストから選択した品名CDをフォームに反映
const selectSuggestion = (suggestion) => {
  form.品名CD = suggestion.品名CD;
  suggestions.value = []; // 候補リストをクリア
};
</script>
<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li><router-link to="/contents">コンテンツ管理</router-link></li>
        <li>新規登録</li>
      </ol> 
      <div class="col-lg-12">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">新規登録</h5>
          </div>
          <el-form :model="form">
            <div class="row space align-center justify-content-between page group contents space_d">
             
              <div class="col-lg-6">
                <label class="col-form-label">品名CD <b style="color:red">*</b> </label>   
                <input
                type="text"
                class="form-control normal"
                v-model="form.品名CD"
                placeholder="品名CDを入力"
              />
              <!-- 候補のドロップダウン -->
              <ul v-if="suggestions.length" class="suggestions">
                <li 
                  v-for="suggestion in suggestions" 
                  :key="suggestion.品名CD" 
                  @click="selectSuggestion(suggestion)"
                >
                  <strong>{{ suggestion.品名CD }}</strong> - {{ suggestion.名称_正式印刷用 }}
                </li>
              </ul>
                
              </div>
              <div class="col-lg-6 align_center">
                <label class="col-form-label">ジャンル<b style="color:red">*</b></label>   
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="form.ジャンル" value="動画">
                  <label class="form-check-label">
                    動画
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="form.ジャンル" value="資料">
                  <label class="form-check-label">
                    資料
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="form.ジャンル" value="テキスト">
                  <label class="form-check-label">
                    テキスト
                  </label>
                </div>
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">タイトル <b style="color:red">*</b> </label>   
                <input type="text" class="form-control normal" v-model="form.タイトル">           
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">詳細 <b style="color:red">*</b> </label>   
                <textarea class="form-control normal" style="height: 100px" v-model="form.詳細"></textarea>             
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">素材アップロード</label>   
                <div id="input_pluralBox">
                  <div id="input_plural" v-for="(file, index) in form.files" :key="index">
                    <input type="file" class="file-button" @change="e => updateFile(e, index)">
                    <input type="button" value="－" class="del pluralBtn" @click="removeFile(index)">
                    <input type="button" value="＋" class="add pluralBtn" @click="addFileInput">
                  </div>
                </div>              
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">YouTube動画リンク</label>   
                <div id="input_pluralBox_b">
                  <div id="input_plural_b" v-for="(link, index) in form.YouTube動画リンク" :key="index">
                    <input type="text" class="form-control normal yu_link" v-model="form.YouTube動画リンク[index]">
                      <input type="button" value="－" class="del pluralBtn" @click="removeYouTubeLink(index)">
                      <input type="button" value="＋" class="add pluralBtn" @click="addYouTubeLinkInput">
                  </div>
                </div>           
              </div>       
              <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                <!-- <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a>        -->
                <button type="button" @click="create" class="button_r none search">登録</button>
              </div>
            </div>              
          </el-form>
        </div>
      </div>
    </div>
  </section>
</template>
<style>
/* ドロップダウンリストのスタイルを適用 */
.suggestions {
  border: 1px solid #ccc;
  max-height: 150px;
  overflow-y: auto;
  list-style: none;
  padding: 0;
  margin: 0;
}
.suggestions li {
  padding: 8px;
  cursor: pointer;
}
.suggestions li:hover {
  background-color: #f0f0f0;
}
</style>